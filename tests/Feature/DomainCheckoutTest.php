<?php

namespace Tests\Feature;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\AuthCodeResult;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainAvailability;
use App\Domain\Registrar\DTOs\DomainInfo;
use App\Domain\Registrar\DTOs\DomainPrice;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\OperationResult;
use App\Domain\Registrar\DTOs\RegistrationResult;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DomainCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['onlinenic.account_currency' => 'EUR', 'onlinenic.customer_billing_currency' => 'EUR', 'domain.registration_markup_percent' => '20']);
        $this->app->instance(RegistrarGateway::class, new CheckoutFakeRegistrar);
    }

    public function test_checkout_rechecks_and_creates_awaiting_payment_order(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $response = $this->actingAs($user)->post(route('checkout.domain.store'), $this->payload());

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame('8.59', $order->provider_cost);
        $this->assertSame('10.31', $order->customer_price);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame('Alice', $order->billing_data['first_name']);
        $this->assertStringNotContainsString('Alice', $order->getRawOriginal('billing_data'));
        $this->assertNull($order->registration_data);
        $this->assertArrayNotHasKey('billing_data', $order->toArray());
    }

    public function test_missing_currency_blocks_order(): void
    {
        config(['onlinenic.customer_billing_currency' => null]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('checkout.domain.store'), $this->payload())->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_duplicate_active_order_is_blocked_and_other_user_cannot_view_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('checkout.domain.store'), $this->payload());
        $this->actingAs($user)->post(route('checkout.domain.store'), $this->payload())->assertSessionHasErrors('checkout');
        $order = Order::firstOrFail();
        $this->actingAs($other)->get(route('orders.show', $order))->assertNotFound();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_invalid_nameservers_are_blocked(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $payload = $this->payload();
        $payload['nameservers'] = ['ns1.example.com', 'ns1.example.com'];

        $this->actingAs($user)->post(route('checkout.domain.store'), $payload)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    private function payload(): array
    {
        $billing = ['first_name' => 'Alice', 'last_name' => 'Example', 'email' => 'alice@example.com', 'phone_number' => '+15555555555', 'country' => 'US', 'city' => 'Los Angeles', 'street' => '1 Main Street', 'state' => 'CA', 'postal_code' => '90001'];

        return ['domain' => 'example.com', 'period' => 1, 'payment_billing' => $billing, 'nameservers' => ['ns1.example.net', 'ns2.example.net']];
    }
}

final class CheckoutFakeRegistrar implements RegistrarGateway
{
    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        return new DomainAvailability($data->domain, true);
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        return new DomainPrice($query->domain, '8.59', $query->period);
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException('Unexpected write.');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        throw new \LogicException('Unexpected info.');
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('Unexpected write.');
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('Unexpected write.');
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        throw new \LogicException('Unexpected read.');
    }
}
