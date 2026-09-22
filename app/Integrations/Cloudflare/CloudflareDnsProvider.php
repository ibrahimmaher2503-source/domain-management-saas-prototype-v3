<?php

namespace App\Integrations\Cloudflare;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsProviderException;

final class CloudflareDnsProvider implements DnsProvider
{
    public function __construct(private readonly CloudflareClient $client) {}

    public function createZone(string $name): array
    {
        $account = (string) config('cloudflare.account_id');
        if ($account === '') {
            throw new DnsProviderException('DNS provider is not configured.');
        }
        $result = $this->client->request('POST', '/zones', ['account' => ['id' => $account], 'name' => $name, 'type' => 'full'])['result'] ?? null;
        if (! is_array($result) || ! isset($result['id'], $result['name_servers'])) {
            throw new AmbiguousDnsWrite('DNS zone creation needs confirmation.');
        }
        if (strcasecmp($result['name'] ?? '', $name) !== 0) {
            throw new AmbiguousDnsWrite('DNS zone identity needs confirmation.');
        }

        return $this->zone($result);
    }

    public function getZone(string $id): array
    {
        return $this->zone($this->client->request('GET', '/zones/'.rawurlencode($id))['result'] ?? null);
    }

    public function findZone(string $name): ?array
    {
        $account = (string) config('cloudflare.account_id');
        if ($account === '') {
            throw new DnsProviderException('DNS provider is not configured.');
        }
        $body = $this->client->request('GET', '/zones', ['name' => $name, 'account.id' => $account]);
        $zones = $body['result'] ?? null;
        if (! is_array($zones)) {
            throw new DnsProviderException('DNS provider returned an invalid zone list.');
        }
        if (count($zones) > 1) {
            throw new DnsProviderException('DNS zone needs operator review.');
        }
        if (count($zones) === 1 && strcasecmp($zones[0]['name'] ?? '', $name) !== 0) {
            throw new DnsProviderException('DNS zone identity could not be confirmed.');
        }

        return count($zones) === 1 ? $this->zone($zones[0]) : null;
    }

    public function listRecords(string $zoneId): array
    {
        $records = [];
        for ($page = 1; ; $page++) {
            $body = $this->client->request('GET', '/zones/'.rawurlencode($zoneId).'/dns_records', ['page' => $page, 'per_page' => 100]);
            if (! is_array($body['result'] ?? null)) {
                throw new DnsProviderException('DNS provider returned an invalid record list.');
            }
            foreach ($body['result'] as $record) {
                $records[] = $this->record($record);
            }
            if ($page >= ($body['result_info']['total_pages'] ?? 1)) {
                return $records;
            }
        }
    }

    public function createRecord(string $zoneId, array $data): array
    {
        $result = $this->client->request('POST', '/zones/'.rawurlencode($zoneId).'/dns_records', $data)['result'] ?? null;
        if (! is_array($result) || ! isset($result['id'])) {
            throw new AmbiguousDnsWrite('DNS change needs confirmation.');
        }

        return $this->record($result);
    }

    public function updateRecord(string $zoneId, string $recordId, array $data): array
    {
        $result = $this->client->request('PATCH', '/zones/'.rawurlencode($zoneId).'/dns_records/'.rawurlencode($recordId), $data)['result'] ?? null;
        if (! is_array($result) || ! isset($result['id'])) {
            throw new AmbiguousDnsWrite('DNS change needs confirmation.');
        }

        return $this->record($result);
    }

    public function deleteRecord(string $zoneId, string $recordId): void
    {
        $this->client->request('DELETE', '/zones/'.rawurlencode($zoneId).'/dns_records/'.rawurlencode($recordId));
    }

    private function zone(mixed $value): array
    {
        if (! is_array($value) || ! is_string($value['id'] ?? null) || ! is_array($value['name_servers'] ?? null)) {
            throw new DnsProviderException('DNS provider returned an invalid zone.');
        }

        return ['id' => $value['id'], 'status' => $value['status'] ?? 'pending', 'nameservers' => $value['name_servers']];
    }

    private function record(mixed $value): array
    {
        if (! is_array($value) || ! is_string($value['id'] ?? null)) {
            throw new DnsProviderException('DNS provider returned an invalid record.');
        }

        return array_intersect_key($value, array_flip(['id', 'type', 'name', 'content', 'data', 'ttl', 'priority', 'proxied', 'proxiable']));
    }
}
