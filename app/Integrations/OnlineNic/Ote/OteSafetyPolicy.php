<?php

namespace App\Integrations\OnlineNic\Ote;

final class OteSafetyPolicy
{
    public const CONFIRMATION = 'I_UNDERSTAND_OTE_WRITES';

    public function check(bool $writesRequested = false): OteSafetyResult
    {
        $failures = [];
        $messages = [];
        $this->require($failures, (string) config('app.env') === 'ote', 'APP_ENV must equal ote.');
        $this->require($failures, (string) config('onlinenic.host') === 'ote.onlinenic.com', 'OnlineNIC host must equal the approved OTE host.');
        $this->require($failures, (int) config('onlinenic.port') > 0, 'OnlineNIC port must be configured.');
        $this->require($failures, filled(config('onlinenic.client_id')) && filled(config('onlinenic.password')), 'OnlineNIC credentials are required.');
        $this->require($failures, filled(config('onlinenic.account_currency')), 'OnlineNIC account currency is required.');
        $this->require($failures, str_ends_with(strtolower((string) config('database.connections.'.config('database.default').'.database')), '_ote'), 'DB_DATABASE must identify the OTE database.');

        $redisPrefix = strtolower((string) config('database.redis.options.prefix'));
        $cachePrefix = strtolower((string) config('cache.prefix'));
        $redisDatabases = [(int) config('database.redis.default.database', -1), (int) config('database.redis.cache.database', -1)];
        $redisSafe = str_starts_with($redisPrefix, 'domain-saas-ote-') && str_starts_with($cachePrefix, 'domain-saas-ote-') && min($redisDatabases) >= 2;
        $this->require($failures, $redisSafe, 'Redis must use an OTE-specific prefix and database.');

        $contactsConfigured = collect(['registrant_contact_id', 'admin_contact_id', 'tech_contact_id', 'billing_contact_id'])->every(fn (string $key): bool => filled(config('onlinenic.'.$key)));
        if (! $contactsConfigured) {
            $messages[] = 'Registration contacts are incomplete; registration validation is blocked.';
        }

        $writesAllowed = $writesRequested && (bool) config('onlinenic.ote.allow_writes') && hash_equals(self::CONFIRMATION, (string) config('onlinenic.ote.confirm'));
        if ($writesRequested) {
            $this->require($failures, $contactsConfigured, 'OTE writes require all configured registration contact IDs.');
            $this->require($failures, (bool) config('onlinenic.ote.allow_writes'), 'OTE writes require ONLINENIC_OTE_ALLOW_WRITES=true.');
            $this->require($failures, hash_equals(self::CONFIRMATION, (string) config('onlinenic.ote.confirm')), 'OTE writes require the exact confirmation phrase.');
        }

        return new OteSafetyResult($failures, $messages, $writesAllowed && $failures === []);
    }

    private function require(array &$failures, bool $condition, string $message): void
    {
        if (! $condition) {
            $failures[] = $message;
        }
    }
}
