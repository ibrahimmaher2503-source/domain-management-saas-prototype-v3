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
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

final class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_refresh_syncs_confirmed_fields_and_records_safe_activity(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->info = new DomainInfo('example.com', '2026-09-22', '2028-09-22', ['NS3.Example.NET', 'ns4.example.net'], 'clientTransferProhibited', true, 'secret-client-id', 'secret-server-id', 1000, 'provider detail');

        $this->actingAs($user)->post(route('domains.sync', $domain))->assertRedirect()->assertSessionHas('domain_notice');

        $domain->refresh();
        $this->assertSame('2026-09-22', $domain->registered_at->toDateString());
        $this->assertSame('2028-09-22', $domain->expires_at->toDateString());
        $this->assertSame(['ns3.example.net', 'ns4.example.net'], $domain->nameservers);
        $this->assertSame('clientTransferProhibited', $domain->provider_status);
        $this->assertNotNull($domain->provider_synced_at);
        $this->assertSame('domain_sync', $domain->registrarOperations()->first()->operation);
        $response = $this->actingAs($user)->get(route('domains.show', $domain));
        $response->assertInertia(fn (Assert $page) => $page->component('Domains/Show')->where('domain.nameservers.0', 'ns3.example.net')->has('activity', 1)->where('activity.0.label', 'Domain synced'));
        $response->assertDontSee('secret-client-id')->assertDontSee('secret-server-id')->assertDontSee('provider detail');
    }

    public function test_unknown_info_fields_do_not_invent_or_erase_values(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->info = new DomainInfo('example.com', null, null, [], null, null, 'read', 'server', 1000, 'OK');

        $this->actingAs($user)->post(route('domains.sync', $domain))->assertRedirect();

        $this->assertNull($domain->fresh()->registered_at);
        $this->assertNull($domain->fresh()->expires_at);
        $this->assertNull($domain->fresh()->provider_status);
        $this->assertSame(['ns1.old.example', 'ns2.old.example'], $domain->fresh()->nameservers);
        $this->assertNotNull($domain->fresh()->provider_synced_at);
    }

    public function test_failed_manual_refresh_returns_safe_error_without_changing_domain(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->infoUnavailable = true;

        $this->actingAs($user)->post(route('domains.sync', $domain))->assertRedirect()->assertSessionHas('domain_error', 'Domain information could not be refreshed. Please try again later.');

        $this->assertNull($domain->fresh()->provider_synced_at);
        $this->assertSame(0, RegistrarOperation::count());
    }

    public function test_other_user_cannot_sync_or_change_nameservers(): void
    {
        [, $domain, $fake] = $this->setupDomain();
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other)->post(route('domains.sync', $domain))->assertNotFound();
        $this->actingAs($other)->put(route('domains.nameservers.update', $domain), ['nameservers' => ['ns1.new.example', 'ns2.new.example']])->assertNotFound();
        $this->actingAs($other)->get(route('domains.show', $domain))->assertNotFound();
        $this->assertSame(0, $fake->infoCalls);
        $this->assertSame(0, $fake->writeCalls);
        $this->assertSame(0, RegistrarOperation::count());
    }

    public function test_nameserver_data_normalizes_and_validates_two_to_six_hosts(): void
    {
        $this->assertSame(['ns1.example.com', 'ns2.example.com'], (new UpdateNameserversData('example.com', [' NS1.EXAMPLE.COM ', 'ns2.example.com']))->nameservers);
        $six = array_map(static fn (int $n): string => "ns{$n}.example.com", range(1, 6));
        $this->assertCount(6, (new UpdateNameserversData('example.com', $six))->nameservers);
        foreach ([['ns1.example.com'], [...$six, 'ns7.example.com'], ['ns1.example.com', 'NS1.EXAMPLE.COM'], ['', 'ns2.example.com'], ['bad host', 'ns2.example.com']] as $invalid) {
            try {
                new UpdateNameserversData('example.com', $invalid);
                $this->fail('Expected invalid nameservers to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_write_is_recorded_before_dispatch_and_confirmed_from_info(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->beforeWrite = static function () use ($domain): void {
            $operation = $domain->registrarOperations()->where('operation', 'update_nameservers')->firstOrFail();
            self::assertSame('pending', $operation->status);
            self::assertNotEmpty($operation->cltrid);
        };
        $fake->reflectWriteInInfo = true;

        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), ['nameservers' => [' NS1.NEW.EXAMPLE ', 'ns2.new.example']])->assertRedirect()->assertSessionHas('domain_notice');

        $this->assertSame(1, $fake->writeCalls);
        $this->assertSame(['ns1.new.example', 'ns2.new.example'], $fake->writeData->nameservers);
        $this->assertSame(['ns1.new.example', 'ns2.new.example'], $domain->fresh()->nameservers);
        $this->assertSame('completed', $domain->registrarOperations()->where('operation', 'update_nameservers')->value('status'));
        $this->assertSame(1, $domain->registrarOperations()->where('operation', 'domain_sync')->count());
    }

    public function test_clear_rejection_fails_operation_without_changing_local_nameservers(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'rejected';

        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), ['nameservers' => ['ns1.new.example', 'ns2.new.example']])->assertRedirect()->assertSessionHas('domain_error');

        $this->assertSame('failed', $domain->registrarOperations()->first()->status);
        $this->assertSame(['ns1.old.example', 'ns2.old.example'], $domain->fresh()->nameservers);
    }

    public function test_ambiguous_matching_read_completes_without_second_write(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'ambiguous';
        $fake->reflectWriteInInfo = true;
        $payload = ['nameservers' => ['ns1.new.example', 'ns2.new.example']];

        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect();
        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect();

        $this->assertSame(1, $fake->writeCalls);
        $this->assertSame('completed', $domain->registrarOperations()->where('operation', 'update_nameservers')->value('status'));
        $this->assertSame(['ns1.new.example', 'ns2.new.example'], $domain->fresh()->nameservers);
    }

    public function test_ambiguous_mismatch_remains_unresolved_and_blocks_repeat(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->writeMode = 'ambiguous';
        $payload = ['nameservers' => ['ns1.new.example', 'ns2.new.example']];

        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect()->assertSessionHas('domain_error');
        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect()->assertSessionHas('domain_error');
        $this->actingAs($user)->post(route('domains.sync', $domain))->assertRedirect();

        $this->assertSame(1, $fake->writeCalls);
        $this->assertSame('ambiguous', $domain->registrarOperations()->where('operation', 'update_nameservers')->value('status'));
        $this->assertSame(['ns1.old.example', 'ns2.old.example'], $domain->fresh()->nameservers);
    }

    public function test_confirmed_write_stays_completed_if_followup_info_fails(): void
    {
        [$user, $domain, $fake] = $this->setupDomain();
        $fake->infoUnavailable = true;
        $payload = ['nameservers' => ['ns1.new.example', 'ns2.new.example']];

        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect()->assertSessionHas('domain_notice');
        $this->actingAs($user)->put(route('domains.nameservers.update', $domain), $payload)->assertRedirect()->assertSessionHas('domain_error');

        $this->assertSame(1, $fake->writeCalls);
        $this->assertSame('completed', $domain->registrarOperations()->first()->status);
        $this->assertSame(['ns1.old.example', 'ns2.old.example'], $domain->fresh()->nameservers);
        $this->assertNull($domain->fresh()->provider_synced_at);
    }

    /** @return array{User, Domain, ManagementFakeRegistrar} */
    private function setupDomain(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $domain = Domain::create(['user_id' => $user->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => ['ns1.old.example', 'ns2.old.example']]);
        $fake = new ManagementFakeRegistrar;
        $this->app->instance(RegistrarGateway::class, $fake);

        return [$user, $domain, $fake];
    }
}

final class ManagementFakeRegistrar implements RegistrarGateway
{
    public int $infoCalls = 0;

    public int $writeCalls = 0;

    public string $writeMode = 'completed';

    public bool $reflectWriteInInfo = false;

    public bool $infoUnavailable = false;

    public ?UpdateNameserversData $writeData = null;

    public ?\Closure $beforeWrite = null;

    public DomainInfo $info;

    public function __construct()
    {
        $this->info = new DomainInfo('example.com', null, null, ['ns1.old.example', 'ns2.old.example'], null, null, 'read', 'server', 1000, 'OK');
    }

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        throw new \LogicException('Unexpected availability check.');
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        throw new \LogicException('Unexpected price check.');
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException('Unexpected registration.');
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        $this->infoCalls++;
        if ($this->infoUnavailable) {
            throw new ProviderRejectedOperation('unavailable', 2001, 'private provider detail');
        }

        return $this->info;
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        $this->writeCalls++;
        $this->writeData = $data;
        ($this->beforeWrite)?->__invoke();
        if ($this->reflectWriteInInfo) {
            $this->info = new DomainInfo($data->domain, null, null, $data->nameservers, null, null, 'read', 'server', 1000, 'OK');
        }
        if ($this->writeMode === 'ambiguous') {
            throw new ProviderAmbiguousResponse('ambiguous');
        }
        if ($this->writeMode === 'rejected') {
            throw new ProviderRejectedOperation('rejected', 2001, 'private provider detail');
        }

        return new OperationResult($cltrid, 'srv-'.$cltrid, 1000, 'OK');
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
