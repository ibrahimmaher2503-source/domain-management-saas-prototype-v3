<?php

namespace App\Providers;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\OnlineNicClient;
use App\Integrations\OnlineNic\OnlineNicRegistrarGateway;
use App\Integrations\OnlineNic\OnlineNicTldResolver;
use App\Integrations\OnlineNic\Transport\TcpSocketTransport;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegistrarGateway::class, function () {
            $clientId = (string) config('onlinenic.client_id', '');
            $password = (string) config('onlinenic.password', '');
            $client = new OnlineNicClient(
                new TcpSocketTransport((string) config('onlinenic.host'), (int) config('onlinenic.port'), (float) config('onlinenic.connect_timeout'), (float) config('onlinenic.read_timeout')),
                new OnlineNicAuthenticator($clientId, $password),
                $clientId,
                $password,
                requestLimit: (int) config('onlinenic.session_request_limit', 150),
            );

            return new OnlineNicRegistrarGateway($client, new OnlineNicTldResolver);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
