<?php

namespace App\Domain\Dns\Contracts;

interface DnsProvider
{
    public function createZone(string $name): array;

    public function getZone(string $id): array;

    public function findZone(string $name): ?array;

    public function listRecords(string $zoneId): array;

    public function createRecord(string $zoneId, array $data): array;

    public function updateRecord(string $zoneId, string $recordId, array $data): array;

    public function deleteRecord(string $zoneId, string $recordId): void;
}
