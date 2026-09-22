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
use App\Integrations\OnlineNic\OnlineNicTldResolver;
use App\Integrations\OnlineNic\Ssl\OnlineNicSslProvider;
use App\Integrations\OnlineNic\Transport\TcpSocketTransport;
use App\Integrations\Paymob\PaymobClient;
use App\Integrations\Paymob\PaymobPaymentGateway;
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
        $this->app->singleton(OnlineNicClient::class, function () {
            $clientId = (string) config('onlinenic.client_id', '');
            $password = (string) config('onlinenic.password', '');

            return new OnlineNicClient(new TcpSocketTransport((string) config('onlinenic.host'), (int) config('onlinenic.port'), (float) config('onlinenic.connect_timeout'), (float) config('onlinenic.read_timeout')), new OnlineNicAuthenticator($clientId, $password), $clientId, $password, requestLimit: (int) config('onlinenic.session_request_limit', 150));
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
        Vite::prefetch(concurrency: 3);
    }
}
