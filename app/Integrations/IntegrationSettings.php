<?php

namespace App\Integrations;

use App\Models\IntegrationSetting;
use Illuminate\Database\QueryException;

final class IntegrationSettings
{
    public function all(string $provider, array $defaults): array
    {
        try {
            $values = IntegrationSetting::query()->where('provider', $provider)->pluck('value', 'key')->all();
        } catch (QueryException) {
            $values = [];
        }

        return array_replace($defaults, $values);
    }

    public function update(string $provider, array $values, array $secretKeys = []): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, $secretKeys, true) && blank($value)) {
                continue;
            }
            IntegrationSetting::updateOrCreate(['provider' => $provider, 'key' => $key], ['value' => (string) $value]);
        }
    }

    public function apply(): void
    {
        foreach (['paymob', 'cloudflare'] as $provider) {
            config([$provider => $this->all($provider, config($provider, []))]);
        }
    }
}
