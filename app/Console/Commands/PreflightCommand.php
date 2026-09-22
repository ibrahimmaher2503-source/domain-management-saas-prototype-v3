<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class PreflightCommand extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Run read-only production readiness checks';

    private bool $failed = false;

    public function handle(): int
    {
        $production = app()->environment('production');
        $this->result($production ? 'PASS' : 'WARN', 'Application environment');
        $this->result(config('app.key') ? 'PASS' : 'FAIL', 'Application key');
        $this->result(! $production || config('app.debug') === false ? ($production ? 'PASS' : 'WARN') : 'FAIL', 'Debug mode');
        $this->result(! $production || str_starts_with((string) config('app.url'), 'https://') ? ($production ? 'PASS' : 'WARN') : 'FAIL', 'HTTPS application URL');

        try {
            DB::connection()->getPdo();
            $this->result('PASS', 'Database connectivity');
        } catch (Throwable) {
            $this->result('FAIL', 'Database connectivity');
        }

        $redisDrivers = config('cache.default') === 'redis' && config('session.driver') === 'redis' && config('queue.default') === 'redis';
        $this->result($redisDrivers ? 'PASS' : ($production ? 'FAIL' : 'WARN'), 'Redis cache, session, and queue configuration');
        if ($redisDrivers) {
            try {
                Redis::connection()->command('ping');
                $this->result('PASS', 'Redis connectivity');
            } catch (Throwable) {
                $this->result('FAIL', 'Redis connectivity');
            }
        }

        $this->result($this->configured(['onlinenic.client_id', 'onlinenic.password']) ? 'PASS' : 'WARN', 'OnlineNIC credentials');
        $this->result($this->configured(['onlinenic.registrant_contact_id', 'onlinenic.admin_contact_id', 'onlinenic.tech_contact_id', 'onlinenic.billing_contact_id']) ? 'PASS' : 'WARN', 'OnlineNIC platform contacts');
        $this->result($this->configured(['paymob.secret_key', 'paymob.public_key', 'paymob.hmac_secret', 'paymob.card_integration_id']) ? 'PASS' : 'WARN', 'Paymob configuration');
        $this->result($this->configured(['cloudflare.api_token', 'cloudflare.account_id']) ? 'PASS' : 'WARN', 'Cloudflare configuration');
        $this->sslProducts();

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function sslProducts(): void
    {
        $enabled = collect(config('ssl.products', []))->filter(fn (array $product) => (bool) ($product['enabled'] ?? false));
        if ($enabled->isEmpty()) {
            $this->result('WARN', 'SSL products');

            return;
        }
        $valid = $enabled->every(fn (array $product) => filled($product['provider_product'] ?? null) && is_numeric($product['customer_price'] ?? null) && filled($product['currency'] ?? null) && ! empty($product['validity_options'] ?? []));
        $this->result($valid ? 'PASS' : 'FAIL', 'SSL products');
    }

    private function configured(array $keys): bool
    {
        return collect($keys)->every(fn (string $key) => filled(config($key)));
    }

    private function result(string $status, string $label): void
    {
        $this->line("[{$status}] {$label}");
        $this->failed = $this->failed || $status === 'FAIL';
    }
}
