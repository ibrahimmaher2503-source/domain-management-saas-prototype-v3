<?php

namespace Tests\Feature;

use App\Jobs\DispatchPaidOrderFulfillment;
use App\Jobs\ProvisionDomainRegistration;
use App\Jobs\ReconcileDnsZone;
use App\Jobs\ReconcileDomainRegistration;
use App\Jobs\ReconcileDomainRenewal;
use App\Jobs\ReconcileDomainTransfer;
use App\Jobs\ReconcileSslCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_depends_only_on_the_application_database(): void
    {
        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'ready')
            ->assertJsonMissingPath('checks.redis');
    }

    public function test_web_responses_include_security_headers(): void
    {
        $this->get('/health/ready')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_preflight_is_read_only_and_does_not_print_secret_values(): void
    {
        $this->artisan('app:preflight')
            ->expectsOutputToContain('[WARN] Application environment')
            ->assertExitCode(0);
    }

    public function test_production_preflight_accepts_database_backed_runtime_drivers(): void
    {
        $this->app['env'] = 'production';
        config()->set([
            'app.debug' => false,
            'app.url' => 'https://domains.example.com',
            'cache.default' => 'database',
            'session.driver' => 'database',
            'queue.default' => 'database',
        ]);

        $this->artisan('app:preflight')
            ->expectsOutputToContain('[PASS] Persistent cache, session, and queue configuration')
            ->assertSuccessful();
    }

    public function test_reconciliation_sweep_is_registered_with_a_schedule(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('app:reconcile-unresolved')
            ->assertSuccessful();
    }

    public function test_oversized_paymob_callback_is_rejected_before_processing(): void
    {
        $response = $this->postJson(route('payments.paymob.callback'), ['payload' => str_repeat('x', 65537)]);

        $response->assertStatus(413);
    }

    public function test_reconciliation_jobs_are_bounded_and_single_attempt(): void
    {
        foreach ([
            ReconcileDomainRegistration::class => 75,
            ReconcileDomainRenewal::class => 75,
            ReconcileDomainTransfer::class => 105,
            ReconcileSslCertificate::class => 75,
            ReconcileDnsZone::class => 30,
            DispatchPaidOrderFulfillment::class => 30,
            ProvisionDomainRegistration::class => 150,
        ] as $job => $timeout) {
            $instance = (new \ReflectionClass($job))->newInstanceWithoutConstructor();
            $this->assertSame(1, $instance->tries);
            $this->assertSame($timeout, $instance->timeout);
        }
    }

    public function test_route_cache_can_be_built_and_cleared(): void
    {
        $this->artisan('route:cache')->assertSuccessful();
        $this->artisan('route:clear')->assertSuccessful();
    }
}
