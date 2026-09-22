<?php

namespace Tests\Feature;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Dns\Services\DnsRecordData;
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
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\Cloudflare\CloudflareClient;
use App\Integrations\Cloudflare\CloudflareDnsProvider;
use App\Integrations\OnlineNic\Exceptions\ProviderRejectedOperation;
use App\Models\DnsZone;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CloudflareDnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_delegates_once_and_sync_activates_zone(): void
    {
        [$user, $domain, $dns, $registrar] = $this->setupDomain();
        $this->actingAs($user)->get(route('domains.show', ['domain' => $domain, 'tab' => 'dns']))->assertInertia(fn (Assert $page) => $page->where('dnsZone', null));
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $zone = $domain->dnsZone;
        $this->assertSame('zone-1', $zone->provider_zone_id);
        $this->assertSame(['amy.ns.cloudflare.com', 'bob.ns.cloudflare.com'], $zone->assigned_nameservers);
        $this->assertSame('pending', $zone->status);
        $this->assertSame(1, $dns->creates);
        $this->assertSame(1, $registrar->writes);
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $this->assertSame(1, $dns->creates);
        $this->assertSame(1, $registrar->writes);
        $dns->zoneStatus = 'active';
        $this->actingAs($user)->post(route('domains.dns.sync', $domain))->assertRedirect();
        $this->assertSame('active', $zone->fresh()->status);
        $this->assertNotNull($zone->fresh()->provider_synced_at);
        $this->actingAs($user)->get(route('domains.show', ['domain' => $domain, 'tab' => 'dns']))->assertInertia(fn (Assert $page) => $page->where('dnsZone.status', 'active')->has('dnsRecords', 0));
    }

    public function test_partial_registrar_failure_keeps_one_pending_zone(): void
    {
        [$user, $domain, $dns, $registrar] = $this->setupDomain();
        $registrar->fail = true;
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $this->assertSame(1, DnsZone::count());
        $this->assertSame('pending', $domain->dnsZone->status);
        $this->assertSame(1, $dns->creates);
        $this->assertSame(2, $registrar->writes); // clear rejection is retryable; no duplicate zone
    }

    public function test_ambiguous_zone_creation_is_not_sent_again(): void
    {
        [$user, $domain, $dns] = $this->setupDomain();
        $dns->ambiguousCreate = true;
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $this->actingAs($user)->post(route('domains.dns.connect', $domain))->assertRedirect();
        $this->assertSame(1, $dns->creates);
        $this->assertNull($domain->dnsZone->provider_zone_id);
    }

    public function test_record_types_and_ownership_and_ambiguous_reconciliation(): void
    {
        [$user, $domain, $dns] = $this->setupDomain();
        $domain->dnsZone()->create(['provider' => 'cloudflare', 'provider_zone_id' => 'zone-1', 'status' => 'active', 'assigned_nameservers' => $dns->zone['nameservers']]);
        $samples = [
            ['type' => 'A', 'content' => '192.0.2.1'],
            ['type' => 'AAAA', 'content' => '2001:db8::1'],
            ['type' => 'CNAME', 'content' => 'target.example.net'],
            ['type' => 'MX', 'content' => 'mail.example.net', 'priority' => 10],
            ['type' => 'TXT', 'content' => 'hello'],
            ['type' => 'SRV', 'priority' => 1, 'weight' => 2, 'port' => 443, 'target' => 'srv.example.net'],
            ['type' => 'CAA', 'flags' => 0, 'tag' => 'issue', 'value' => 'letsencrypt.org'],
        ];
        foreach ($samples as $sample) {
            $this->actingAs($user)->post(route('domains.dns.records.store', $domain), ['name' => 'www', 'ttl' => 1, ...$sample])->assertRedirect();
        }
        $this->assertCount(7, $dns->records);
        $this->assertSame(['priority' => 1, 'weight' => 2, 'port' => 443, 'target' => 'srv.example.net'], $dns->records[5]['data']);
        $this->assertSame(['flags' => 0, 'tag' => 'issue', 'value' => 'letsencrypt.org'], $dns->records[6]['data']);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->post(route('domains.dns.connect', $domain))->assertNotFound();
        $this->actingAs($other)->get(route('domains.show', ['domain' => $domain, 'tab' => 'dns']))->assertNotFound();
        $this->actingAs($other)->post(route('domains.dns.records.store', $domain), ['type' => 'A'])->assertNotFound();
        $this->actingAs($other)->patch(route('domains.dns.records.update', [$domain, 'r1']), ['type' => 'A'])->assertNotFound();
        $this->actingAs($other)->delete(route('domains.dns.records.destroy', [$domain, 'r1']), ['confirm' => true])->assertNotFound();
        $dns->ambiguousRecord = true;
        $this->actingAs($user)->post(route('domains.dns.records.store', $domain), ['type' => 'A', 'name' => 'api', 'content' => '192.0.2.2', 'ttl' => 1])->assertRedirect();
        $this->assertTrue($domain->dnsZone->operations()->where('status', 'ambiguous')->exists());
        $this->actingAs($user)->post(route('domains.dns.records.store', $domain), ['type' => 'A', 'name' => 'api', 'content' => '192.0.2.2', 'ttl' => 1])->assertRedirect();
        $this->assertCount(8, $dns->records);
        $dns->zoneStatus = 'active';
        $this->actingAs($user)->post(route('domains.dns.sync', $domain))->assertRedirect();
        $this->assertFalse($domain->dnsZone->operations()->where('status', 'ambiguous')->exists());
        $this->actingAs($user)->patch(route('domains.dns.records.update', [$domain, 'r1']), ['type' => 'A', 'name' => 'www', 'content' => '192.0.2.9', 'ttl' => 300])->assertRedirect();
        $this->assertSame('192.0.2.9', $dns->records[0]['content']);
        $this->actingAs($user)->delete(route('domains.dns.records.destroy', [$domain, 'r1']), ['confirm' => true])->assertRedirect();
        $this->assertCount(7, $dns->records);
    }

    public function test_invalid_records_and_missing_credentials_fail_safely(): void
    {
        foreach ([['type' => 'A', 'content' => 'bad'], ['type' => 'AAAA', 'content' => 'bad'], ['type' => 'MX', 'content' => 'mail.example.com', 'priority' => -1], ['type' => 'A', 'name' => 'www.anotherdomain.com', 'content' => '192.0.2.1']] as $case) {
            try {
                DnsRecordData::fromInput(['name' => '@', 'ttl' => 1, ...$case], 'example.com');
                $this->fail('Expected validation error.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        config(['cloudflare.api_token' => 'test-secret', 'cloudflare.account_id' => '']);
        $provider = new CloudflareDnsProvider(new CloudflareClient);
        $this->expectException(DnsProviderException::class);
        $provider->createZone('example.com');
    }

    public function test_missing_token_and_frontend_secret_safety(): void
    {
        config(['cloudflare.api_token' => '', 'cloudflare.account_id' => 'account-1']);
        $provider = new CloudflareDnsProvider(new CloudflareClient);
        try {
            $provider->findZone('example.com');
            $this->fail('Expected missing token to fail.');
        } catch (DnsProviderException) {
            $this->assertTrue(true);
        }
        [$user, $domain] = $this->setupDomain();
        config(['cloudflare.api_token' => 'frontend-secret-sentinel']);
        $this->actingAs($user)->get(route('domains.show', $domain))->assertDontSee('frontend-secret-sentinel');
    }

    public function test_ambiguous_update_and_delete_require_read_reconciliation(): void
    {
        [$user, $domain, $dns] = $this->setupDomain();
        $domain->dnsZone()->create(['provider' => 'cloudflare', 'provider_zone_id' => 'zone-1', 'status' => 'active', 'assigned_nameservers' => $dns->zone['nameservers']]);
        $dns->records = [['id' => 'r1', 'type' => 'A', 'name' => 'www.example.com', 'content' => '192.0.2.1', 'ttl' => 1, 'proxied' => false]];
        $dns->ambiguousUpdate = true;
        $payload = ['type' => 'A', 'name' => 'www', 'content' => '192.0.2.2', 'ttl' => 1];
        $this->actingAs($user)->patch(route('domains.dns.records.update', [$domain, 'r1']), $payload)->assertRedirect();
        $this->actingAs($user)->patch(route('domains.dns.records.update', [$domain, 'r1']), $payload)->assertRedirect();
        $this->assertSame(1, $dns->updates);
        $dns->zoneStatus = 'active';
        $this->actingAs($user)->post(route('domains.dns.sync', $domain))->assertRedirect();
        $this->assertFalse($domain->dnsZone->operations()->where('status', 'ambiguous')->exists());
        $dns->ambiguousDelete = true;
        $this->actingAs($user)->delete(route('domains.dns.records.destroy', [$domain, 'r1']), ['confirm' => true])->assertRedirect();
        $this->actingAs($user)->post(route('domains.dns.sync', $domain))->assertRedirect();
        $this->assertFalse($domain->dnsZone->operations()->where('status', 'ambiguous')->exists());
        $this->assertSame(1, $dns->deletes);
    }

    private function setupDomain(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $domain = Domain::create(['user_id' => $user->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => ['old1.example.net', 'old2.example.net']]);
        $dns = new FakeDnsProvider;
        $registrar = new FakeDnsRegistrar;
        $this->app->instance(DnsProvider::class, $dns);
        $this->app->instance(RegistrarGateway::class, $registrar);

        return [$user, $domain, $dns, $registrar];
    }
}

final class FakeDnsProvider implements DnsProvider
{
    public int $creates = 0;

    public bool $ambiguousCreate = false;

    public bool $ambiguousRecord = false;

    public bool $ambiguousUpdate = false;

    public bool $ambiguousDelete = false;

    public int $updates = 0;

    public int $deletes = 0;

    public string $zoneStatus = 'pending';

    public array $zone = ['id' => 'zone-1', 'status' => 'pending', 'nameservers' => ['amy.ns.cloudflare.com', 'bob.ns.cloudflare.com']];

    public array $records = [];

    public function findZone(string $name): ?array
    {
        return null;
    }

    public function createZone(string $name): array
    {
        $this->creates++;
        if ($this->ambiguousCreate) {
            throw new AmbiguousDnsWrite;
        }

        return $this->zone;
    }

    public function getZone(string $id): array
    {
        return ['id' => $id, 'status' => $this->zoneStatus, 'nameservers' => $this->zone['nameservers']];
    }

    public function listRecords(string $zoneId): array
    {
        return $this->records;
    }

    public function createRecord(string $zoneId, array $data): array
    {
        $record = ['id' => 'r'.(count($this->records) + 1), ...$data];
        $this->records[] = $record;
        if ($this->ambiguousRecord) {
            $this->ambiguousRecord = false;
            throw new AmbiguousDnsWrite;
        }

        return $record;
    }

    public function updateRecord(string $zoneId, string $recordId, array $data): array
    {
        $this->updates++;
        foreach ($this->records as &$record) {
            if ($record['id'] === $recordId) {
                $record = ['id' => $recordId, ...$data];
                if ($this->ambiguousUpdate) {
                    $this->ambiguousUpdate = false;
                    throw new AmbiguousDnsWrite;
                }

                return $record;
            }
        }
        throw new \LogicException;
    }

    public function deleteRecord(string $zoneId, string $recordId): void
    {
        $this->deletes++;
        $this->records = array_values(array_filter($this->records, fn ($record) => $record['id'] !== $recordId));
        if ($this->ambiguousDelete) {
            $this->ambiguousDelete = false;
            throw new AmbiguousDnsWrite;
        }
    }
}

final class FakeDnsRegistrar implements RegistrarGateway
{
    public int $writes = 0;

    public bool $fail = false;

    public array $nameservers = ['old1.example.net', 'old2.example.net'];

    public function checkDomain(CheckDomainData $data): DomainAvailability
    {
        throw new \LogicException;
    }

    public function getDomainPrice(DomainPriceQuery $query): DomainPrice
    {
        throw new \LogicException;
    }

    public function registerDomain(DomainRegistrationData $data, string $cltrid): RegistrationResult
    {
        throw new \LogicException;
    }

    public function renewDomain(RenewDomainData $data, string $cltrid): RenewalResult
    {
        throw new \LogicException;
    }

    public function getDomainInfo(string $domain): DomainInfo
    {
        return new DomainInfo($domain, null, null, $this->nameservers, null, null, 'read', 'server', 1000, 'OK');
    }

    public function updateNameservers(UpdateNameserversData $data, string $cltrid): OperationResult
    {
        $this->writes++;
        if ($this->fail) {
            throw new ProviderRejectedOperation('reject', 2001, 'secret');
        } $this->nameservers = $data->nameservers;

        return new OperationResult($cltrid, 'server', 1000, 'OK');
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
