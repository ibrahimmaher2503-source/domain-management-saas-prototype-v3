<?php

namespace Tests\Feature;

use App\Jobs\ProvisionDomainRegistration;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PaymobPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['paymob.secret_key' => 'sk_test', 'paymob.public_key' => 'pk_test', 'paymob.card_integration_id' => '1234', 'paymob.hmac_secret' => 'hmac-secret']);
        Queue::fake();
    }

    public function test_start_payment_creates_intention_and_redirects(): void
    {
        Http::fake(['https://accept.paymob.com/v1/intention/' => Http::response(['id' => 'pi_test_1', 'intention_order_id' => 987, 'client_secret' => 'cs_test', 'special_reference' => 'order-1', 'status' => 'created'], 201)]);
        [$user, $order] = $this->order();

        $response = $this->actingAs($user)->post(route('orders.pay', $order));

        $response->assertRedirect('https://accept.paymob.com/unifiedcheckout/?publicKey=pk_test&clientSecret=cs_test');
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider' => 'paymob', 'provider_order_id' => 987, 'status' => 'pending']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Token sk_test') && $request->url() === 'https://accept.paymob.com/v1/intention/' && $request['amount'] === 1031 && $request['currency'] === 'EGP' && $request['payment_methods'] === [1234] && $request['special_reference'] === 'order-'.$order->id && $request['billing_data']['first_name'] === 'Billing' && $request['billing_data']['email'] === 'payer@example.com');
    }

    public function test_verified_callback_marks_payment_and_order_paid_idempotently(): void
    {
        [$user, $order] = $this->order();
        $payment = Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'pending', 'amount' => '10.31', 'currency' => 'EGP', 'provider_order_id' => 987, 'provider_reference' => 'order-'.$order->id]);
        $payload = $this->callbackPayload(true, false, 1031, 'EGP', 987, 77);

        $this->postJson(route('payments.paymob.callback'), $payload)->assertOk();
        $this->postJson(route('payments.paymob.callback'), $payload)->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'provider_transaction_id' => '77']);
        $this->assertSame('paid', $order->fresh()->status);
        Queue::assertPushed(ProvisionDomainRegistration::class, fn (ProvisionDomainRegistration $job) => $job->connection === 'database');
        Queue::assertPushed(ProvisionDomainRegistration::class, 1);
    }

    public function test_different_user_cannot_pay_and_paid_order_cannot_start(): void
    {
        Http::fake();
        [$user, $order] = $this->order();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->post(route('orders.pay', $order))->assertNotFound();
        $order->update(['status' => 'paid']);
        $this->actingAs($user)->post(route('orders.pay', $order))->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_second_start_reuses_the_pending_payment(): void
    {
        Http::fake(['https://accept.paymob.com/v1/intention/' => Http::response(['id' => 'pi_test_1', 'intention_order_id' => 987, 'client_secret' => 'cs_test', 'status' => 'created'], 201)]);
        [$user, $order] = $this->order();
        $this->actingAs($user)->post(route('orders.pay', $order));
        $this->actingAs($user)->post(route('orders.pay', $order));
        $this->assertDatabaseCount('payments', 1);
        Http::assertSentCount(1);
    }

    public function test_legacy_registrar_contact_data_does_not_substitute_for_payment_billing(): void
    {
        Http::fake();
        [$user, $order] = $this->order();
        $order->update(['billing_data' => null]);

        $this->actingAs($user)->post(route('orders.pay', $order))->assertSessionHasErrors('payment');

        Http::assertNothingSent();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'failed']);
    }

    public function test_invalid_hmac_and_amount_mismatch_do_not_change_state(): void
    {
        [$user, $order] = $this->order();
        Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'paymob', 'status' => 'pending', 'amount' => '10.31', 'currency' => 'EGP', 'provider_order_id' => 987, 'provider_reference' => 'order-'.$order->id]);
        $payload = $this->callbackPayload(true, false, 999, 'EGP', 987, 77);
        $this->postJson(route('payments.paymob.callback'), $payload)->assertOk();
        $this->assertDatabaseHas('payments', ['status' => 'pending']);
        $this->assertSame('awaiting_payment', $order->fresh()->status);

        $payload['hmac'] = 'wrong';
        $this->postJson(route('payments.paymob.callback'), $payload)->assertForbidden();
    }

    private function order(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $order = Order::create(['user_id' => $user->id, 'type' => 'domain_registration', 'status' => 'awaiting_payment', 'domain' => 'example.com', 'tld' => 'com', 'registration_period' => 1, 'provider' => 'onlinenic', 'provider_cost' => '8.59', 'customer_price' => '10.31', 'currency' => 'EGP', 'premium' => false, 'registration_data' => ['registrant' => ['name' => 'Legacy Registrar', 'email' => 'legacy@example.com']], 'billing_data' => ['first_name' => 'Billing', 'last_name' => 'Customer', 'email' => 'payer@example.com', 'phone_number' => '+201000000000', 'country' => 'EG', 'city' => 'Cairo', 'street' => '1 Main', 'state' => 'Cairo', 'postal_code' => '11511'], 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);

        return [$user, $order];
    }

    private function callbackPayload(bool $success, bool $pending, int $amount, string $currency, int $orderId, int $transactionId): array
    {
        $object = ['amount_cents' => $amount, 'created_at' => '2026-09-22T12:00:00.000000', 'currency' => $currency, 'error_occured' => false, 'has_parent_transaction' => false, 'id' => $transactionId, 'integration_id' => 1234, 'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true, 'is_voided' => false, 'order' => ['id' => $orderId], 'owner' => 1, 'pending' => $pending, 'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'success' => $success];
        $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];
        $value = static fn (string $field): string => is_array(data_get($object, $field)) ? (string) data_get($object, $field.'.id') : (is_bool(data_get($object, $field)) ? (data_get($object, $field) ? 'true' : 'false') : (string) data_get($object, $field));
        $object['hmac'] = hash_hmac('sha512', implode('', array_map($value, $fields)), 'hmac-secret');

        return ['type' => 'TRANSACTION', 'obj' => $object];
    }
}
