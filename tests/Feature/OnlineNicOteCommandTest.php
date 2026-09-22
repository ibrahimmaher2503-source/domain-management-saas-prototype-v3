<?php

namespace Tests\Feature;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnlineNicOteCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'ote',
            'onlinenic.host' => 'ote.onlinenic.com',
            'onlinenic.port' => 30009,
            'onlinenic.client_id' => null,
            'onlinenic.password' => null,
            'onlinenic.account_currency' => null,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => 'domain_management_ote',
            'database.redis.options.prefix' => 'domain-saas-ote-',
            'database.redis.default.database' => '10',
            'database.redis.cache.database' => '11',
            'cache.prefix' => 'domain-saas-ote-cache-',
        ]);
    }

    public function test_preflight_reports_failures_without_printing_secret_values(): void
    {
        $this->artisan('onlinenic:ote-preflight')
            ->expectsOutputToContain('[FAIL] OnlineNIC credentials')
            ->assertExitCode(1);
    }

    public function test_validation_fails_before_resolving_provider_when_safety_gate_is_invalid(): void
    {
        $this->app->bind(RegistrarGateway::class, fn (): never => throw new \LogicException('provider must not resolve'));

        $this->artisan('onlinenic:ote-validate')
            ->expectsOutputToContain('Safety gate failed')
            ->assertExitCode(1);
    }

    public function test_write_mode_requires_confirmation_and_allow_flag(): void
    {
        config(['onlinenic.client_id' => 'id', 'onlinenic.password' => 'secret', 'onlinenic.account_currency' => 'USD']);

        $this->artisan('onlinenic:ote-validate', ['--writes' => true])
            ->expectsOutputToContain('ONLINENIC_OTE_ALLOW_WRITES=true')
            ->assertExitCode(1);
    }
}
