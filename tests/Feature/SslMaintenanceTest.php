<?php

namespace Tests\Feature;

use App\Domain\Ssl\Contracts\SslProvider;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Jobs\ReconcileSslCertificate;
use App\Models\Domain;
use App\Models\Order;
use App\Models\SslCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SslMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private FakeSslProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->provider = new FakeSslProvider;
        $this->app->instance(SslProvider::class, $this->provider);
    }

    public function test_pending_owner_can_change_to_provider_email_and_resend_once(): void
    {
        [$user, $certificate] = $this->certificate('pending_validation');

        $this->actingAs($user)->post(route('ssl.approver-email', $certificate), ['approver_email' => 'hostmaster@example.com', 'confirm_email' => 'hostmaster@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('hostmaster@example.com', $certificate->fresh()->approver_email);
        $this->assertDatabaseHas('registrar_operations', ['operation' => 'change_approver_email', 'status' => 'completed']);

        $this->actingAs($user)->post(route('ssl.resend-approver-email', $certificate))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('ssl.resend-approver-email', $certificate))->assertSessionHasErrors('ssl');
        $this->assertSame(1, $this->provider->resendApproverCalls);
    }

    public function test_arbitrary_approver_email_and_cross_user_actions_are_rejected(): void
    {
        [$owner, $certificate] = $this->certificate('pending_validation');
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('ssl.approver-email', $certificate), ['approver_email' => 'other@example.com', 'confirm_email' => 'other@example.com'])->assertSessionHasErrors('ssl');
        $this->actingAs($other)->post(route('ssl.cancel', $certificate), ['confirm' => true])->assertNotFound();
        $this->assertSame(0, $this->provider->changeCalls);
    }

    public function test_cancel_requires_confirmation_and_valid_status(): void
    {
        [$user, $pending] = $this->certificate('pending_validation');
        [, $issued] = $this->certificate('issued', $user, 'second.example.com');

        $this->actingAs($user)->post(route('ssl.cancel', $pending), [])->assertSessionHasErrors('confirm');
        $this->actingAs($user)->post(route('ssl.cancel', $pending), ['confirm' => true])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->actingAs($user)->post(route('ssl.cancel', $issued), ['confirm' => true])->assertSessionHasErrors('ssl');
        $this->assertSame(1, $this->provider->cancelCalls);
    }

    public function test_reissue_reuses_parse_csr_rejects_private_keys_and_wrong_domains(): void
    {
        [$user, $certificate] = $this->certificate('issued');

        $this->actingAs($user)->post(route('ssl.reissue', $certificate), ['csr' => '-----BEGIN PRIVATE KEY-----', 'confirm' => true])->assertSessionHasErrors('ssl');
        $this->provider->parsedDomain = 'wrong.example.com';
        $this->actingAs($user)->post(route('ssl.reissue', $certificate), ['csr' => 'valid-csr', 'confirm' => true])->assertSessionHasErrors('ssl');
        $this->provider->parsedDomain = 'example.com';
        $this->actingAs($user)->post(route('ssl.reissue', $certificate), ['csr' => 'valid-csr', 'confirm' => true])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->provider->parseCalls);
        $this->assertSame(1, $this->provider->reissueCalls);
        $this->assertSame('processing', $certificate->fresh()->status);
        $this->assertDatabaseHas('registrar_operations', ['operation' => 'reissue_certificate', 'status' => 'completed']);
        $this->assertDatabaseMissing('registrar_operations', ['provider_message' => 'valid-csr']);
        $this->assertTrue($this->provider->operationExistedBeforeWrite);
    }

    public function test_ambiguous_cancel_is_not_blindly_retried(): void
    {
        [$user, $certificate] = $this->certificate('pending_validation');
        $this->provider->ambiguousCancel = true;

        $this->actingAs($user)->post(route('ssl.cancel', $certificate), ['confirm' => true])->assertSessionHasErrors('ssl');
        $this->actingAs($user)->post(route('ssl.cancel', $certificate), ['confirm' => true])->assertSessionHasErrors('ssl');

        $this->assertSame(1, $this->provider->cancelCalls);
        $this->assertDatabaseHas('registrar_operations', ['operation' => 'cancel_certificate', 'status' => 'ambiguous']);
    }

    public function test_info_reconciles_ambiguous_cancel(): void
    {
        [, $certificate] = $this->certificate('pending_validation');
        $operation = $certificate->order->registrarOperations()->create(['user_id' => $certificate->user_id, 'domain_id' => $certificate->domain_id, 'provider' => 'onlinenic', 'operation' => 'cancel_certificate', 'cltrid' => 'tx-reconcile', 'status' => 'ambiguous', 'started_at' => now()]);
        $this->provider->infoStatus = 'cancelled';

        (new ReconcileSslCertificate($certificate->id))->handle($this->provider);

        $this->assertSame('cancelled', $certificate->fresh()->status);
        $this->assertSame('completed', $operation->fresh()->status);
    }

    public function test_ambiguous_email_change_keeps_old_email_and_is_not_retried(): void
    {
        [$user, $certificate] = $this->certificate('pending_validation');
        $this->provider->ambiguousChange = true;
        $payload = ['approver_email' => 'hostmaster@example.com', 'confirm_email' => 'hostmaster@example.com'];

        $this->actingAs($user)->post(route('ssl.approver-email', $certificate), $payload)->assertSessionHasErrors('ssl');
        $this->actingAs($user)->post(route('ssl.approver-email', $certificate), $payload)->assertSessionHasErrors('ssl');

        $this->assertSame('admin@example.com', $certificate->fresh()->approver_email);
        $this->assertSame(1, $this->provider->changeCalls);
        $this->assertDatabaseHas('registrar_operations', ['operation' => 'change_approver_email', 'status' => 'ambiguous']);
    }

    public function test_info_reconciles_ambiguous_reissue_after_processing_starts(): void
    {
        [$user, $certificate] = $this->certificate('issued');
        $this->provider->ambiguousReissue = true;

        $this->actingAs($user)->post(route('ssl.reissue', $certificate), ['csr' => 'valid-csr', 'confirm' => true])->assertSessionHasErrors('ssl');
        $operation = $certificate->order->registrarOperations()->where('operation', 'reissue_certificate')->firstOrFail();
        $this->provider->infoStatus = 'processing';
        (new ReconcileSslCertificate($certificate->id))->handle($this->provider);

        $this->assertSame('processing', $certificate->fresh()->status);
        $this->assertSame('completed', $operation->fresh()->status);
        $this->assertSame(1, $this->provider->reissueCalls);
    }

    public function test_fulfillment_resend_is_issued_only_and_rate_limited(): void
    {
        [$user, $issued] = $this->certificate('issued');
        [, $pending] = $this->certificate('pending_validation', $user, 'second.example.com');

        $this->actingAs($user)->post(route('ssl.resend-fulfillment-email', $pending))->assertSessionHasErrors('ssl');
        $this->actingAs($user)->post(route('ssl.resend-fulfillment-email', $issued))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('ssl.resend-fulfillment-email', $issued))->assertSessionHasErrors('ssl');
        $this->assertSame(1, $this->provider->resendFulfillmentCalls);
    }

    private function certificate(string $status, ?User $user = null, string $name = 'example.com'): array
    {
        $user ??= User::factory()->create();
        $domain = Domain::create(['user_id' => $user->id, 'name' => $name, 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => []]);
        $order = Order::create(['user_id' => $user->id, 'type' => 'ssl_certificate', 'status' => 'completed', 'domain' => $name, 'tld' => 'com', 'registration_period' => 1, 'provider' => 'onlinenic', 'customer_price' => 10, 'currency' => 'USD', 'registration_data' => [], 'nameservers' => []]);
        $certificate = SslCertificate::create(['user_id' => $user->id, 'domain_id' => $domain->id, 'order_id' => $order->id, 'provider' => 'onlinenic', 'provider_order_id' => (string) $order->id, 'product_key' => 'dv', 'provider_product' => 'RapidSSL', 'status' => $status, 'validation_type' => 'dv', 'approver_email' => 'admin@example.com', 'expires_at' => $status === 'issued' ? now()->addYear() : null]);
        $order->update(['ssl_certificate_id' => $certificate->id]);

        return [$user, $certificate];
    }
}

final class FakeSslProvider implements SslProvider
{
    public int $cancelCalls = 0;

    public int $changeCalls = 0;

    public int $resendApproverCalls = 0;

    public int $parseCalls = 0;

    public int $reissueCalls = 0;

    public string $parsedDomain = 'example.com';

    public bool $operationExistedBeforeWrite = false;

    public bool $ambiguousCancel = false;

    public bool $ambiguousChange = false;

    public bool $ambiguousReissue = false;

    public string $infoStatus = 'issued';

    public int $resendFulfillmentCalls = 0;

    public function parseCsr(string $product, string $csr): array
    {
        $this->parseCalls++;

        return ['domain' => $this->parsedDomain];
    }

    public function getApproverEmails(string $domain): array
    {
        return ['admin@example.com', 'hostmaster@example.com'];
    }

    public function orderCertificate(array $request, string $transactionId): array
    {
        return ['order_id' => '1', 'price' => null];
    }

    public function getCertificateOrder(string $orderId): array
    {
        return ['status' => $this->infoStatus, 'provider_status' => strtoupper($this->infoStatus), 'issued_at' => null, 'expires_at' => null];
    }

    public function cancelCertificate(string $orderId, string $transactionId): void
    {
        $this->beforeWrite();
        $this->cancelCalls++;
        if ($this->ambiguousCancel) {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
    }

    public function changeApproverEmail(string $orderId, string $email, string $transactionId): void
    {
        $this->beforeWrite();
        $this->changeCalls++;
        if ($this->ambiguousChange) {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
    }

    public function resendApproverEmail(string $orderId, string $transactionId): void
    {
        $this->beforeWrite();
        $this->resendApproverCalls++;
    }

    public function reissueCertificate(string $orderId, string $csr, string $transactionId): void
    {
        $this->beforeWrite();
        $this->reissueCalls++;
        if ($this->ambiguousReissue) {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
    }

    public function resendFulfillmentEmail(string $orderId, string $transactionId): void
    {
        $this->beforeWrite();
        $this->resendFulfillmentCalls++;
    }

    private function beforeWrite(): void
    {
        $this->operationExistedBeforeWrite = DB::table('registrar_operations')->where('status', 'pending')->exists();
    }
}
