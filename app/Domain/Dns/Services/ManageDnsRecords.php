<?php

namespace App\Domain\Dns\Services;

use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Models\DnsOperation;
use App\Models\DnsZone;
use Illuminate\Support\Facades\DB;

final class ManageDnsRecords
{
    public function __construct(private readonly DnsProvider $provider) {}

    public function records(DnsZone $zone): array
    {
        abort_unless($zone->status === 'active' && $zone->provider_zone_id, 422);

        return $this->provider->listRecords($zone->provider_zone_id);
    }

    public function write(DnsZone $zone, string $operation, ?string $recordId, ?array $data): string
    {
        $records = $this->records($zone);
        $current = $recordId ? collect($records)->firstWhere('id', $recordId) : null;
        if ($recordId && ! $current) {
            abort(404);
        }
        if ($current && ! in_array($current['type'], DnsRecordData::TYPES, true)) {
            abort(422);
        }
        if ($operation === 'update' && $data['type'] !== $current['type']) {
            abort(422);
        }
        $record = DB::transaction(function () use ($zone, $operation, $recordId, $data, $current, $records): ?DnsOperation {
            DnsZone::query()->whereKey($zone->id)->lockForUpdate()->firstOrFail();
            if ($zone->operations()->whereIn('status', ['pending', 'ambiguous'])->exists()) {
                return null;
            }

            return $zone->operations()->create([
                'operation' => $operation,
                'record_id' => $recordId,
                'record_type' => $data['type'] ?? $current['type'],
                'record_name' => $data['name'] ?? $current['name'],
                'status' => 'pending',
                'request_data' => ['fingerprint' => $data ? $this->fingerprint($data) : null, 'previous_ids' => $operation === 'create' ? array_column($records, 'id') : []],
            ]);
        });
        if (! $record) {
            return 'ambiguous';
        }
        try {
            $result = match ($operation) {
                'create' => $this->provider->createRecord($zone->provider_zone_id, $data),
                'update' => $this->provider->updateRecord($zone->provider_zone_id, $recordId, $data),
                'delete' => $this->provider->deleteRecord($zone->provider_zone_id, $recordId),
            };
            $record->update(['status' => 'completed', 'record_id' => $result['id'] ?? $recordId]);

            return 'completed';
        } catch (AmbiguousDnsWrite) {
            $record->update(['status' => 'ambiguous']);

            return 'ambiguous';
        } catch (DnsProviderException $exception) {
            $record->update(['status' => 'failed']);
            throw $exception;
        }
    }

    public function reconcile(DnsZone $zone): void
    {
        $records = $this->records($zone);
        foreach ($zone->operations()->whereIn('status', ['pending', 'ambiguous'])->get() as $operation) {
            $matching = collect($records)->first(function (array $record) use ($operation): bool {
                if ($operation->operation === 'delete') {
                    return false;
                }
                if ($operation->operation === 'update' && $record['id'] !== $operation->record_id) {
                    return false;
                }
                if ($operation->operation === 'create' && in_array($record['id'], $operation->request_data['previous_ids'] ?? [], true)) {
                    return false;
                }

                return $this->fingerprint($record) === ($operation->request_data['fingerprint'] ?? null);
            });
            if ($matching || ($operation->operation === 'delete' && ! collect($records)->contains('id', $operation->record_id))) {
                $operation->update(['status' => 'completed', 'record_id' => $matching['id'] ?? $operation->record_id]);
            }
        }
    }

    private function fingerprint(array $record): string
    {
        $type = $record['type'];
        $fields = ['type' => $type, 'name' => strtolower($record['name']), 'ttl' => (int) $record['ttl']];
        if (in_array($type, ['SRV', 'CAA'], true)) {
            $fields['data'] = $record['data'] ?? [];
        } else {
            $fields['content'] = $record['content'] ?? '';
        }
        if ($type === 'MX') {
            $fields['priority'] = (int) ($record['priority'] ?? 0);
        }
        if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            $fields['proxied'] = (bool) ($record['proxied'] ?? false);
        }

        return hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR));
    }
}
