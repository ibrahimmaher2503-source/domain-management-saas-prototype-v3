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
use App\Domain\Registrar\DTOs\RenewalResult;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Domain\Registrar\DTOs\RequestTransferData;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\TransferRequestResult;
use App\Domain\Registrar\DTOs\TransferStatusResult;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use App\Integrations\OnlineNic\OnlineNicSettings;
use App\Models\Domain;
use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class AdminDomainOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_customer_with_one_time_activation_and_safe_audit(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $response = $this->actingAs($admin)->post(route('admin.customers.store'), ['name' => 'Ahmed Ali', 'email' => 'ahmed@example.com']);

        $customer = User::where('email', 'ahmed@example.com')->firstOrFail();
        $response->assertRedirect(route('admin.customers.show', $customer))->assertSessionHas('activation_link');
        $this->assertTrue($customer->activation_pending);
        $this->assertFalse(Hash::check('ahmed@example.com', $customer->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $customer->email]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'customer.created', 'resource_id' => $customer->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'customer.activation_link.generated', 'resource_id' => $customer->id]);
        $this->actingAs(User::factory()->create())->post(route('admin.customers.store'), ['name' => 'No', 'email' => 'no@example.com'])->assertForbidden();
    }

    public function test_activation_token_expires_and_is_one_time_use(): void
    {
        $customer = User::factory()->create(['activation_pending' => true, 'email_verified_at' => null]);
        $token = Password::broker()->createToken($customer);
        $payload = ['email' => $customer->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'];

        $this->post(route('password.store'), $payload)->assertRedirect(route('login'));
        $this->assertFalse($customer->fresh()->activation_pending);
        $this->assertNotNull($customer->fresh()->email_verified_at);
        $this->post(route('password.store'), $payload)->assertSessionHasErrors('email');

        $expired = Password::broker()->createToken($customer);
        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();
        $this->post(route('password.store'), array_merge($payload, ['token' => $expired]))->assertSessionHasErrors('email');
    }

    public function test_admin_imports_only_provider_confirmed_domain_without_order_or_write(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $gateway = new ImportReadOnlyGateway(new DomainInfo('example.com', '2026-01-02', '2027-01-02', ['ns1.example.net', 'ns2.example.net'], 'ok', true, 'safe-client-id', 'safe-server-id', 1000, 'ok'));
        $this->app->instance(RegistrarGateway::class, $gateway);

        $this->actingAs($admin)->post(route('admin.domains.import.store'), ['domain' => '  EXAMPLE.COM ', 'provider' => 'onlinenic', 'customer_id' => $customer->id, 'note' => 'Purchased manually for customer'])->assertRedirect();

        $domain = Domain::where('name', 'example.com')->firstOrFail();
        $this->assertSame($customer->id, $domain->user_id);
        $this->assertSame('admin_import', $domain->acquisition_source);
        $this->assertNull($domain->order_id);
        $this->assertSame(['getDomainInfo'], $gateway->calls);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'domain.imported', 'resource_id' => $domain->id]);
        $this->actingAs($customer)->get(route('domains'))->assertSee('example.com');
        $this->actingAs($customer)->get(route('domains.show', $domain))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('domains.show', $domain))->assertNotFound();
    }

    public function test_unknown_and_duplicate_domains_are_rejected_without_reassignment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Domain::create(['user_id' => $owner->id, 'name' => 'owned.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => []]);
        $gateway = new ImportReadOnlyGateway(null);
        $this->app->instance(RegistrarGateway::class, $gateway);

        $this->actingAs($admin)->post(route('admin.domains.import.store'), ['domain' => 'owned.com', 'provider' => 'onlinenic', 'customer_id' => $other->id])->assertSessionHasErrors('domain');
        $this->assertSame($owner->id, Domain::where('name', 'owned.com')->value('user_id'));
        $this->assertSame([], $gateway->calls);

        $this->actingAs($admin)->post(route('admin.domains.import.store'), ['domain' => 'external.com', 'provider' => 'onlinenic', 'customer_id' => $other->id])->assertSessionHasErrors('domain');
        $this->assertDatabaseMissing('domains', ['name' => 'external.com']);
        $this->assertSame(['getDomainInfo'], $gateway->calls);
    }

    public function test_admin_updates_encrypted_onlinenic_settings_without_returning_the_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.providers.onlinenic.update'), [
            'host' => 'ote.onlinenic.com', 'port' => 30009, 'client_id' => 'account-1', 'password' => 'secret-password',
            'account_currency' => 'USD', 'customer_billing_currency' => 'USD', 'registrant_contact_id' => 'r1',
            'admin_contact_id' => 'a1', 'tech_contact_id' => 't1', 'billing_contact_id' => 'b1',
        ])->assertRedirect();

        $stored = IntegrationSetting::query()->where('provider', 'onlinenic')->where('key', 'password')->firstOrFail();
        $this->assertNotSame('secret-password', $stored->getRawOriginal('value'));
        $this->assertSame('secret-password', $stored->value);
        $this->assertSame('secret-password', app(OnlineNicSettings::class)->value('password'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'provider.settings.updated', 'resource_type' => 'provider']);
    }

    public function test_admin_updates_paymob_and_cloudflare_settings_without_exposing_secrets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put(route('admin.providers.paymob.update'), [
            'base_url' => 'https://accept.paymob.com', 'secret_key' => 'paymob-secret', 'public_key' => 'paymob-public', 'hmac_secret' => 'paymob-hmac',
            'card_integration_id' => 123, 'mode' => 'test', 'payment_expiration' => 3600, 'notification_url' => 'https://example.com/payments/callback', 'redirection_url' => 'https://example.com/payments/return',
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('admin.providers.cloudflare.update'), ['api_base' => 'https://api.cloudflare.com/client/v4', 'api_token' => 'cloudflare-secret', 'account_id' => 'account-1'])->assertRedirect();

        $this->assertNotSame('paymob-secret', IntegrationSetting::where('provider', 'paymob')->where('key', 'secret_key')->firstOrFail()->getRawOriginal('value'));
        $this->assertNotSame('cloudflare-secret', IntegrationSetting::where('provider', 'cloudflare')->where('key', 'api_token')->firstOrFail()->getRawOriginal('value'));
        $this->assertDatabaseHas('integration_settings', ['provider' => 'paymob', 'key' => 'card_integration_id']);
        $this->assertDatabaseHas('integration_settings', ['provider' => 'cloudflare', 'key' => 'account_id']);
    }
}

final class ImportReadOnlyGateway implements RegistrarGateway
{
    public array $calls = [];

    public function __construct(private readonly ?DomainInfo $info) {}

    public function getDomainInfo(string $domain): DomainInfo
    {
        $this->calls[] = 'getDomainInfo';
        if (! $this->info) {
            throw new InvalidProviderResponse('not controlled');
        }

        return $this->info;
    }

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        throw new \LogicException('write/unneeded call');
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        throw new \LogicException('write/unneeded call');
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException('write call');
    }

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult
    {
        throw new \LogicException('write call');
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('write call');
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        throw new \LogicException('write call');
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        throw new \LogicException('secret call');
    }

    public function requestRegistrarTransfer(RequestTransferData $data, string $cltrid): TransferRequestResult
    {
        throw new \LogicException('write call');
    }

    public function getRegistrarTransferStatus(string $domain): TransferStatusResult
    {
        throw new \LogicException('unneeded call');
    }

    public function cancelRegistrarTransfer(string $domain, string $cltrid): OperationResult
    {
        throw new \LogicException('write call');
    }
}
