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
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderRejectedOperation;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DomainSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_sync_uses_conclusive_state_and_preserves_indeterminate_state(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        foreach ([[true, true], [false, false], [null, false]] as [$providerState, $expected]) {
            $fake->transferLocked = $providerState;
            $this->actingAs($user)->post(route('domains.sync', $domain))->assertRedirect();
            $this->assertSame($expected, $domain->fresh()->transfer_locked);
        }
    }

    public function test_owner_can_lock_and_unlock_with_operation_persisted_before_each_write(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->reflectWriteInInfo = true;
        $fake->beforeWrite = static function () use ($domain): void {
            $operation = $domain->registrarOperations()->where('operation', 'set_transfer_lock')->latest('id')->firstOrFail();
            self::assertSame('pending', $operation->status);
            self::assertNotEmpty($operation->cltrid);
        };

        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect()->assertSessionHas('domain_notice');
        $this->assertTrue($domain->fresh()->transfer_locked);
        $this->assertTrue($fake->writes[0]->locked);
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => false])->assertRedirect()->assertSessionHas('domain_notice');
        $this->assertFalse($domain->fresh()->transfer_locked);
        $this->assertFalse($fake->writes[1]->locked);
        $this->assertSame(['completed', 'completed'], $domain->registrarOperations()->where('operation', 'set_transfer_lock')->pluck('status')->all());
    }

    public function test_rejected_lock_fails_safely_and_other_user_cannot_write(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'rejected';
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect()->assertSessionHas('domain_error', 'Transfer lock could not be changed. Please try again later.');
        $this->assertSame('failed', $domain->registrarOperations()->where('operation', 'set_transfer_lock')->value('status'));
        $this->assertNull($domain->fresh()->transfer_locked);

        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertNotFound();
        $this->assertCount(1, $fake->writes);
    }

    public function test_ambiguous_lock_is_reconciled_without_retry_when_info_confirms_it(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'ambiguous';
        $fake->reflectWriteInInfo = true;

        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect()->assertSessionHas('domain_notice');
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect();

        $this->assertCount(1, $fake->writes);
        $this->assertTrue($domain->fresh()->transfer_locked);
        $this->assertSame('completed', $domain->registrarOperations()->where('operation', 'set_transfer_lock')->value('status'));
    }

    public function test_unconfirmed_ambiguous_lock_stays_ambiguous_and_blocks_repeat(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'ambiguous';

        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect()->assertSessionHas('domain_error', 'The registrar is still confirming this security change.');
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => true])->assertRedirect();

        $this->assertCount(1, $fake->writes);
        $this->assertSame('ambiguous', $domain->registrarOperations()->where('operation', 'set_transfer_lock')->value('status'));
        $this->assertNull($domain->fresh()->transfer_locked);
    }

    public function test_unlock_rejection_and_ambiguous_reconciliation_are_safe(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $domain->update(['transfer_locked' => true]);
        $fake->transferLocked = true;
        $fake->writeMode = 'rejected';
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => false])->assertRedirect()->assertSessionHas('domain_error');
        $this->assertTrue($domain->fresh()->transfer_locked);

        $fake->writeMode = 'ambiguous';
        $fake->reflectWriteInInfo = true;
        $this->actingAs($user)->put(route('domains.security.transfer-lock', $domain), ['locked' => false])->assertRedirect()->assertSessionHas('domain_notice');

        $this->assertCount(2, $fake->writes);
        $this->assertFalse($domain->fresh()->transfer_locked);
        $this->assertSame(['failed', 'completed'], $domain->registrarOperations()->where('operation', 'set_transfer_lock')->pluck('status')->all());
    }

    public function test_auth_code_requires_current_password_is_owner_only_and_is_never_persisted(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $this->actingAs($user)->postJson(route('domains.security.auth-code', $domain), ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame(0, $fake->authCalls);

        $other = User::factory()->create(['email_verified_at' => now(), 'password' => Hash::make('correct-password')]);
        $this->actingAs($other)->postJson(route('domains.security.auth-code', $domain), ['password' => 'correct-password'])->assertNotFound();
        $response = $this->actingAs($user)->postJson(route('domains.security.auth-code', $domain), ['password' => 'correct-password']);
        $response->assertOk()->assertJson(['auth_code' => 'EPP-SECRET'])->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(1, $fake->authCalls);
        $operation = RegistrarOperation::where('operation', 'get_auth_code')->firstOrFail();
        $this->assertNull($operation->safe_request_metadata);
        $this->assertNull($operation->provider_metadata);
        $this->assertStringNotContainsString('EPP-SECRET', json_encode([$domain->fresh()->toArray(), $operation->toArray()]));
        $this->actingAs($user)->get(route('domains.show', $domain))->assertInertia(fn (Assert $page) => $page->component('Domains/Show')->missing('auth_code'))->assertDontSee('EPP-SECRET');
    }

    public function test_auth_code_endpoint_is_rate_limited(): void
    {
        [$user, $domain] = $this->setupDomain();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->postJson(route('domains.security.auth-code', $domain), ['password' => 'wrong'])->assertUnprocessable();
        }
        $this->actingAs($user)->postJson(route('domains.security.auth-code', $domain), ['password' => 'wrong'])->assertTooManyRequests();
    }

    /** @return array{User, Domain, SecurityFakeRegistrar} */
    private function setupDomain(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => Hash::make('correct-password')]);
        $domain = Domain::create(['user_id' => $user->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'transfer_locked' => null, 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);
        $fake = new SecurityFakeRegistrar;
        $this->app->instance(RegistrarGateway::class, $fake);

        return [$user, $domain, $fake];
    }
}

final class SecurityFakeRegistrar implements RegistrarGateway
{
    public ?bool $transferLocked = null;
    public string $writeMode = 'completed';
    public bool $reflectWriteInInfo = false;
    public int $authCalls = 0;
    /** @var list<TransferLockData> */
    public array $writes = [];
    public ?\Closure $beforeWrite = null;

    public function checkDomain(CheckDomainData $data): DomainAvailability { throw new \LogicException; }
    public function getDomainPrice(DomainPriceQuery $query): DomainPrice { throw new \LogicException; }
    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult { throw new \LogicException; }
    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult { throw new \LogicException; }

    public function getDomainInfo(string $domain): DomainInfo
    {
        return new DomainInfo($domain, null, null, ['ns1.example.net', 'ns2.example.net'], null, $this->transferLocked, 'read', 'server', 1000, 'OK');
    }

    public function setTransferLock(TransferLockData $data, string $cltrid): OperationResult
    {
        $this->writes[] = $data;
        ($this->beforeWrite)?->__invoke();
        if ($this->reflectWriteInInfo) {
            $this->transferLocked = $data->locked;
        }
        if ($this->writeMode === 'ambiguous') {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
        if ($this->writeMode === 'rejected') {
            throw new ProviderRejectedOperation('rejected', 2001, 'private provider detail');
        }

        return new OperationResult($cltrid, 'server', 1000, 'OK');
    }

    public function getAuthCode(string $domain): AuthCodeResult
    {
        $this->authCalls++;

        return new AuthCodeResult('EPP-SECRET', 'auth-read-'.uniqid(), 'server', 1000);
    }
}
