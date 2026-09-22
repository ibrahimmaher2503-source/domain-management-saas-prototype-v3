<?php

namespace App\Console\Commands;

use App\Integrations\OnlineNic\Ote\OteSafetyPolicy;
use Illuminate\Console\Command;

final class OnlineNicOtePreflightCommand extends Command
{
    protected $signature = 'onlinenic:ote-preflight';

    protected $description = 'Audit isolated OnlineNIC OTE configuration without connecting';

    public function handle(OteSafetyPolicy $policy): int
    {
        $result = $policy->check();
        $checks = [
            'APP_ENV' => (string) config('app.env') === 'ote',
            'OnlineNIC host' => (string) config('onlinenic.host') === 'ote.onlinenic.com',
            'OnlineNIC port' => (int) config('onlinenic.port') > 0,
            'OnlineNIC credentials' => filled(config('onlinenic.client_id')) && filled(config('onlinenic.password')),
            'OnlineNIC account currency' => filled(config('onlinenic.account_currency')),
            'Registration contacts' => collect(['registrant_contact_id', 'admin_contact_id', 'tech_contact_id', 'billing_contact_id'])->every(fn (string $key): bool => filled(config('onlinenic.'.$key))),
            'OTE database isolation' => str_ends_with(strtolower((string) config('database.connections.'.config('database.default').'.database')), '_ote'),
            'OTE Redis isolation' => $this->redisIsolated(),
            'OTE write flag' => (bool) config('onlinenic.ote.allow_writes') ? 'enabled; explicit command confirmation still required' : 'disabled',
            'OTE test domain' => filled(config('onlinenic.ote.test_domain')) ? 'configured' : 'not configured',
        ];
        foreach ($checks as $label => $state) {
            $status = $state === true ? 'PASS' : ($state === false ? 'FAIL' : 'WARN');
            $this->line("[{$status}] {$label}".($state === true || $state === false ? '' : ': '.$state));
        }
        foreach ($result->messages() as $message) {
            $this->warn('[WARN] '.$message);
        }

        return $result->failures() === [] ? self::SUCCESS : self::FAILURE;
    }

    private function redisIsolated(): bool
    {
        return str_starts_with(strtolower((string) config('database.redis.options.prefix')), 'domain-saas-ote-')
            && str_starts_with(strtolower((string) config('cache.prefix')), 'domain-saas-ote-')
            && (int) config('database.redis.default.database', -1) >= 2
            && (int) config('database.redis.cache.database', -1) >= 2;
    }
}
