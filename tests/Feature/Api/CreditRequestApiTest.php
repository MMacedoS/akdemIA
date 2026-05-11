<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Tenant\Tenant;
use App\Models\User;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreditRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_list_and_view_credit_requests_via_api(): void
    {
        config()->set('services.payment.api_token', 'test-token');
        config()->set('services.payment.api_base_url', 'https://api.mercadopago.com');

        Http::fake([
            'https://api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD-900',
                'external_reference' => 'credits-admin-900',
                'status' => 'created',
                'status_detail' => 'pending',
                'transactions' => [
                    'payments' => [[
                        'id' => 'PAY-900',
                        'status' => 'pending',
                        'status_detail' => 'waiting_transfer',
                        'payment_method' => [
                            'ticket_url' => 'https://example.test/ticket-900',
                            'qr_code' => 'pix-code-900',
                            'qr_code_base64' => 'pix-image-900',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Academia API',
            'slug' => 'academia-api',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'profile_type' => Role::ADMIN->value,
            'is_active' => true,
        ]);
        $admin->acceptRequiredPolicies();

        $tenant->users()->attach($admin->id, ['role' => Role::ADMIN->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($admin, $tenant);

        $createResponse = $this->postJson('/api/v1/credits/requests', [
            'credits_requested' => 25,
            'note' => 'Comprar pacote do mes',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $requestId = (int) $createResponse->json('data.id');

        $createResponse->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.requester_user_id', $admin->id)
            ->assertJsonPath('data.credits_requested', 25)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_provider_payment_id', 'PAY-900')
            ->assertJsonPath('data.payment_ticket_url', 'https://example.test/ticket-900')
            ->assertJsonPath('data.pix_payload', 'pix-code-900')
            ->assertJsonPath('data.note', 'Comprar pacote do mes');

        $this->getJson('/api/v1/credits/requests', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.credits_requested', 25);

        $this->getJson('/api/v1/credits/requests/' . $requestId, [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.payment_status_detail', 'waiting_transfer');
    }

    public function test_student_can_create_list_and_view_credit_requests_via_api(): void
    {
        config()->set('services.payment.api_token', 'test-token');
        config()->set('services.payment.api_base_url', 'https://api.mercadopago.com');

        Http::fake([
            'https://api.mercadopago.com/v1/orders' => Http::response([
                'id' => 'ORD-901',
                'external_reference' => 'credits-user-901',
                'status' => 'created',
                'status_detail' => 'pending',
                'transactions' => [
                    'payments' => [[
                        'id' => 'PAY-901',
                        'status' => 'pending',
                        'status_detail' => 'waiting_transfer',
                        'payment_method' => [
                            'ticket_url' => 'https://example.test/ticket-901',
                            'qr_code' => 'pix-code-901',
                            'qr_code_base64' => 'pix-image-901',
                        ],
                    ]],
                ],
            ], 201),
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Academia Student',
            'slug' => 'academia-student',
            'is_active' => true,
        ]);

        $student = User::factory()->create([
            'profile_type' => Role::STUDENT->value,
            'is_active' => true,
        ]);
        $student->acceptRequiredPolicies();

        $tenant->users()->attach($student->id, ['role' => Role::STUDENT->value]);

        $token = app(TenantAuthService::class)->generateTenantToken($student, $tenant);

        $createResponse = $this->postJson('/api/v1/credits/requests', [
            'credits_requested' => 10,
            'note' => 'Recarga pelo app',
        ], [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ]);

        $requestId = (int) $createResponse->json('data.id');

        $createResponse->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.requester_user_id', $student->id)
            ->assertJsonPath('data.target_user_id', $student->id)
            ->assertJsonPath('data.credits_requested', 10)
            ->assertJsonPath('data.payment_provider_payment_id', 'PAY-901')
            ->assertJsonPath('data.payment_ticket_url', 'https://example.test/ticket-901')
            ->assertJsonPath('data.note', 'Recarga pelo app');

        $this->getJson('/api/v1/credits/requests', [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.requester_user_id', $student->id);

        $this->getJson('/api/v1/credits/requests/' . $requestId, [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-Slug' => $tenant->slug,
        ])->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.pix_payload', 'pix-code-901');
    }
}
