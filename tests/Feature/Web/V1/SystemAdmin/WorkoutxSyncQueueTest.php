<?php

namespace Tests\Feature\Web\V1\SystemAdmin;

use App\Enums\Role;
use App\Jobs\SyncWorkoutxCatalogGifJob;
use App\Jobs\SyncWorkoutxCatalogPageJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WorkoutxSyncQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_sync_route_queues_catalog_job_with_status_feedback(): void
    {
        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', 'secret-from-settings');

        Bus::fake();

        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('system-admin.settings.workoutx.sync'));

        $response->assertRedirect(route('system-admin.settings.workoutx.edit'));
        $response->assertSessionHas('status', 'Sincronizacao do catalogo enfileirada. O processamento vai ocorrer em paginas, com intervalo entre requests para evitar limite da API.');

        Bus::assertDispatched(SyncWorkoutxCatalogPageJob::class, function (SyncWorkoutxCatalogPageJob $job) use ($user): bool {
            return $job->offset === 0
                && $job->limit === null
                && $job->requestedByUserId === $user->id;
        });

        $this->actingAs($user)
            ->get(route('system-admin.settings.workoutx.edit'))
            ->assertOk()
            ->assertSee('Na fila')
            ->assertSee('Sincronizacao enfileirada e aguardando processamento.')
            ->assertSee('Sincronizacao em andamento');
    }

    public function test_system_admin_sync_route_blocks_new_queue_when_sync_is_already_running(): void
    {
        config()->set('services.workoutx.enabled', true);
        config()->set('services.workoutx.api_base_url', 'https://api.workoutxapp.com/v1');
        config()->set('services.workoutx.api_key', 'secret-from-settings');

        Bus::fake();

        Cache::forever('workoutx:catalog_sync_status', [
            'state' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => null,
            'message' => 'Sincronizacao em andamento com intervalo entre paginas para respeitar o limite da API.',
            'progress' => [
                'synced' => 10,
                'created' => 5,
                'updated' => 3,
                'unchanged' => 2,
                'next_offset' => 20,
                'total_cached' => 10,
            ],
        ]);

        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('system-admin.settings.workoutx.sync'));

        $response->assertRedirect(route('system-admin.settings.workoutx.edit'));
        $response->assertSessionHas('errors', function (ViewErrorBag $errors): bool {
            return in_array(
                'Ja existe uma sincronizacao do catalogo WorkoutX em andamento. Aguarde a fila terminar antes de iniciar outra.',
                $errors->getBag('default')->all(),
                true,
            );
        });

        Bus::assertNothingDispatched();

        $this->actingAs($user)
            ->get(route('system-admin.settings.workoutx.edit'))
            ->assertOk()
            ->assertSee('Em andamento')
            ->assertSee('Sincronizacao em andamento')
            ->assertSee('O botao fica bloqueado enquanto a fila estiver aguardando ou processando paginas.');
    }

    public function test_system_admin_gif_sync_route_queues_catalog_gif_job_with_status_feedback(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('system-admin.settings.workoutx.audit.gifs-sync'));

        $response->assertRedirect(route('system-admin.settings.workoutx.audit'));
        $response->assertSessionHas('status', 'Download dos GIFs pendentes enfileirado. O processamento vai preencher o storage_path a partir do remote_gif_url.');

        Bus::assertDispatched(SyncWorkoutxCatalogGifJob::class, function (SyncWorkoutxCatalogGifJob $job) use ($user): bool {
            return $job->afterRemoteExerciseId === null
                && $job->limit === null
                && $job->requestedByUserId === $user->id;
        });

        $this->actingAs($user)
            ->get(route('system-admin.settings.workoutx.audit'))
            ->assertOk()
            ->assertSee('Na fila')
            ->assertSee('Download dos GIFs pendentes enfileirado e aguardando processamento.')
            ->assertSee('Download de GIFs em andamento');
    }

    public function test_system_admin_gif_sync_route_blocks_new_queue_when_gif_sync_is_already_running(): void
    {
        Bus::fake();

        Cache::forever('workoutx:catalog_gif_sync_status', [
            'state' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'requested_by_user_id' => null,
            'message' => 'Download dos GIFs pendentes em andamento.',
            'progress' => [
                'processed' => 10,
                'downloaded' => 8,
                'failed' => 2,
                'pending_local_file' => 4,
                'next_remote_exercise_id' => '0100',
            ],
        ]);

        $user = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_system_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->post(route('system-admin.settings.workoutx.audit.gifs-sync'));

        $response->assertRedirect(route('system-admin.settings.workoutx.audit'));
        $response->assertSessionHas('errors', function (ViewErrorBag $errors): bool {
            return in_array(
                'Ja existe uma sincronizacao de GIFs do catalogo WorkoutX em andamento. Aguarde a fila terminar antes de iniciar outra.',
                $errors->getBag('default')->all(),
                true,
            );
        });

        Bus::assertNothingDispatched();
    }
}
