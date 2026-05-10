<?php

namespace Tests\Unit\Transformers\Profile;

use App\Models\User;
use App\Transformers\Profile\StudentTrainerTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTrainerTransformerTest extends TestCase
{
    use RefreshDatabase;

    public function test_transform_assigned_builds_subdomain_landing_url_when_root_domain_is_available(): void
    {
        config()->set('app.url', 'https://app.academai.com.br');
        config()->set('app.landing_root_domain', 'academai.com.br');

        $trainee = User::factory()->create();
        $trainee->publicProfile()->create([
            'slug' => 'trainer-pro',
            'is_published' => true,
        ]);

        $payload = app(StudentTrainerTransformer::class)->transformAssigned($trainee);

        $this->assertSame('https://trainer-pro.academai.com.br', $payload['landing_url']);
    }

    public function test_transform_assigned_falls_back_to_public_route_when_root_domain_is_unavailable(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('app.landing_root_domain', null);

        $trainee = User::factory()->create();
        $trainee->publicProfile()->create([
            'slug' => 'trainer-pro',
            'is_published' => true,
        ]);

        $payload = app(StudentTrainerTransformer::class)->transformAssigned($trainee);

        $this->assertSame('/pro/trainer-pro', parse_url((string) $payload['landing_url'], PHP_URL_PATH));
    }
}
