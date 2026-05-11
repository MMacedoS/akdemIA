<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Tenant\Plan;
use App\Models\Tenant\Tenant;
use App\Models\Tenant\TenantSubscription;
use App\Models\User;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExerciseMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function configureIsolatedLocalDisk(): void
    {
        $root = storage_path('framework/testing/disks/local-exercise-media-' . bin2hex(random_bytes(5)));

        config()->set('filesystems.disks.local.root', $root);

        app('files')->ensureDirectoryExists($root);
        app('filesystem')->forgetDisk('local');
    }

    private function createTenant(string $slug = 'academia-imagem'): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'Academia Imagem',
            'slug' => $slug,
            'is_active' => true,
        ]);

        $plan = Plan::query()->create([
            'name' => 'Plano Imagem ' . $slug,
            'price' => 99.90,
            'max_students' => 100,
            'max_trainers' => 10,
            'ai_limit' => 1000,
            'features' => [],
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => null,
            'status' => 'active',
            'ai_usage' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        return $tenant;
    }

    private function configureIsolatedPublicDisk(): void
    {
        $root = storage_path('framework/testing/disks/public-exercise-media-' . bin2hex(random_bytes(5)));

        config()->set('filesystems.disks.public.root', $root);
        config()->set('filesystems.disks.public.url', '/storage');

        app('files')->ensureDirectoryExists($root);
        app('filesystem')->forgetDisk('public');
    }

    public function test_exercise_media_endpoint_requires_valid_token(): void
    {
        $this->configureIsolatedPublicDisk();
        $this->configureIsolatedLocalDisk();
        Storage::disk('local')->put('exercises/bench-press.gif', 'gif-binary');

        $this->get('/api/v1/workouts/exercises/media/bench-press')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_exercise_media_endpoint_returns_gif_for_authenticated_tenant_user(): void
    {
        $this->configureIsolatedPublicDisk();
        $this->configureIsolatedLocalDisk();
        Storage::disk('local')->put('exercises/bench-press.gif', 'gif-binary');

        $tenant = $this->createTenant();
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $this->get('/api/v1/workouts/exercises/media/bench-press', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=3600, private')
            ->assertHeader('content-type', 'image/gif');
    }

    public function test_exercise_media_endpoint_migrates_legacy_public_gif_to_private_storage(): void
    {
        $this->configureIsolatedPublicDisk();
        $this->configureIsolatedLocalDisk();
        Storage::disk('public')->put('exercises/bench-press.gif', 'gif-binary');

        $tenant = $this->createTenant('academia-imagem-legado');
        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $this->get('/api/v1/workouts/exercises/media/bench-press', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk();

        $this->assertTrue(Storage::disk('local')->exists('exercises/bench-press.gif'));
        $this->assertFalse(Storage::disk('public')->exists('exercises/bench-press.gif'));
    }
}
