<?php

namespace App\Providers;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Dns\Contracts\DnsProvider;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Ssl\Contracts\SslProvider;
use App\Integrations\Cloudflare\CloudflareDnsProvider;
use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\OnlineNicClient;
use App\Integrations\OnlineNic\OnlineNicRegistrarGateway;
use App\Integrations\OnlineNic\OnlineNicSettings;
use App\Integrations\OnlineNic\OnlineNicTldResolver;
use App\Integrations\OnlineNic\Ssl\OnlineNicSslProvider;
use App\Integrations\OnlineNic\Transport\TcpSocketTransport;
use App\Integrations\Paymob\PaymobClient;
use App\Integrations\Paymob\PaymobPaymentGateway;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DnsProvider::class, CloudflareDnsProvider::class);
        $this->app->singleton(PaymentGateway::class, fn () => new PaymobPaymentGateway(new PaymobClient));
        $this->app->scoped(OnlineNicClient::class, function () {
            $settings = app(OnlineNicSettings::class)->all();
            $clientId = (string) ($settings['client_id'] ?? '');
            $password = (string) ($settings['password'] ?? '');

            return new OnlineNicClient(new TcpSocketTransport((string) ($settings['host'] ?? config('onlinenic.host')), (int) ($settings['port'] ?? config('onlinenic.port')), (float) config('onlinenic.connect_timeout'), (float) config('onlinenic.read_timeout')), new OnlineNicAuthenticator($clientId, $password), $clientId, $password, requestLimit: (int) config('onlinenic.session_request_limit', 150));
        });
        $this->app->singleton(RegistrarGateway::class, function () {
            return new OnlineNicRegistrarGateway($this->app->make(OnlineNicClient::class), new OnlineNicTldResolver);
        });
        $this->app->singleton(SslProvider::class, fn () => new OnlineNicSslProvider($this->app->make(OnlineNicClient::class)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        Vite::prefetch(concurrency: 3);
    }
}
