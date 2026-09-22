<?php

namespace Tests\Unit;

use App\Integrations\OnlineNic\Ote\OteSafetyPolicy;
use Tests\TestCase;

final class OteSafetyPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'ote',
            'onlinenic.host' => 'ote.onlinenic.com',
            'onlinenic.port' => 30009,
            'onlinenic.client_id' => 'hidden-client',
            'onlinenic.password' => 'hidden-password',
            'onlinenic.account_currency' => 'USD',
            'onlinenic.registrant_contact_id' => 'hidden-contact',
            'onlinenic.admin_contact_id' => 'hidden-contact',
            'onlinenic.tech_contact_id' => 'hidden-contact',
            'onlinenic.billing_contact_id' => 'hidden-contact',
            'onlinenic.ote.allow_writes' => false,
            'onlinenic.ote.confirm' => null,
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'domain_management_ote',
            'database.redis.options.prefix' => 'domain-saas-ote-',
            'database.redis.default.database' => '10',
            'database.redis.cache.database' => '11',
            'cache.prefix' => 'domain-saas-ote-cache-',
        ]);
    }

    public function test_ote_isolation_accepts_a_safe_read_only_configuration(): void
    {
        $result = (new OteSafetyPolicy)->check();

        $this->assertSame([], $result->failures());
        $this->assertFalse($result->writesAllowed());
    }

    public function test_production_environment_and_host_are_rejected(): void
    {
        config(['app.env' => 'production', 'onlinenic.host' => 'www.onlinenic.com']);

        $result = (new OteSafetyPolicy)->check();

        $this->assertContains('APP_ENV must equal ote.', $result->failures());
        $this->assertContains('OnlineNIC host must equal the approved OTE host.', $result->failures());
        $this->assertStringNotContainsString('hidden-password', implode(' ', $result->messages()));
    }

    public function test_writes_require_every_explicit_gate(): void
    {
        config(['onlinenic.ote.allow_writes' => true, 'onlinenic.ote.confirm' => 'wrong']);

        $result = (new OteSafetyPolicy)->check(writesRequested: true);

        $this->assertContains('OTE writes require the exact confirmation phrase.', $result->failures());
        $this->assertFalse($result->writesAllowed());
    }

    public function test_production_database_and_shared_redis_namespace_are_rejected(): void
    {
        config([
            'database.connections.mysql.database' => 'domain_management',
            'database.redis.options.prefix' => 'domain-saas-',
            'database.redis.default.database' => '0',
            'database.redis.cache.database' => '1',
            'cache.prefix' => 'domain-saas-cache-',
        ]);

        $result = (new OteSafetyPolicy)->check();

        $this->assertContains('DB_DATABASE must identify the OTE database.', $result->failures());
        $this->assertContains('Redis must use an OTE-specific prefix and database.', $result->failures());
    }
}
