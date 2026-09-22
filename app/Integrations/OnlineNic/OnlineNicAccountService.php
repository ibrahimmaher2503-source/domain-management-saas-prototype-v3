<?php

namespace App\Integrations\OnlineNic;

use App\Integrations\OnlineNic\Commands\GetAccountBalanceCommand;
use App\Integrations\OnlineNic\Exceptions\InvalidProviderResponse;
use Illuminate\Support\Facades\Cache;

final class OnlineNicAccountService
{
    private const CACHE_KEY = 'admin.providers.onlinenic.balance';

    public function __construct(private readonly OnlineNicClient $client) {}

    /** @return array{amount:string,currency:string,checked_at:string} */
    public function balance(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, 60, function (): array {
            $this->client->ensureAuthenticated();
            $response = $this->client->execute(new GetAccountBalanceCommand);
            $amount = $response->data['balance'] ?? null;
            if (! is_string($amount) || ! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
                throw new InvalidProviderResponse('OnlineNIC returned an invalid balance.');
            }

            return ['amount' => $amount, 'currency' => (string) config('onlinenic.account_currency', ''), 'checked_at' => now()->toIso8601String()];
        });
    }
}
