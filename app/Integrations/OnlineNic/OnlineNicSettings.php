<?php

namespace App\Integrations\OnlineNic;

use App\Integrations\IntegrationSettings;

final class OnlineNicSettings
{
    private const KEYS = [
        'host', 'port', 'client_id', 'password', 'account_currency', 'customer_billing_currency',
        'registrant_contact_id', 'admin_contact_id', 'tech_contact_id', 'billing_contact_id',
    ];

    public function __construct(private readonly IntegrationSettings $store) {}

    public function all(): array
    {
        return array_replace(config('onlinenic'), array_intersect_key($this->store->all('onlinenic', config('onlinenic')), array_flip(self::KEYS)));
    }

    public function value(string $key): mixed
    {
        return $this->all()[$key] ?? config('onlinenic.'.$key);
    }

    public function form(): array
    {
        $settings = $this->all();

        return [
            'host' => $settings['host'] ?? '',
            'port' => (int) ($settings['port'] ?? 30009),
            'client_id' => $settings['client_id'] ?? '',
            'password_configured' => filled($settings['password'] ?? null),
            'account_currency' => $settings['account_currency'] ?? '',
            'customer_billing_currency' => $settings['customer_billing_currency'] ?? '',
            'registrant_contact_id' => $settings['registrant_contact_id'] ?? '',
            'admin_contact_id' => $settings['admin_contact_id'] ?? '',
            'tech_contact_id' => $settings['tech_contact_id'] ?? '',
            'billing_contact_id' => $settings['billing_contact_id'] ?? '',
        ];
    }

    public function update(array $values): void
    {
        $this->store->update('onlinenic', array_intersect_key($values, array_flip(self::KEYS)), ['password']);
    }
}
