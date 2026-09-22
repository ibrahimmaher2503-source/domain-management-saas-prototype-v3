<?php

namespace App\Domain\Domains\Services;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DomainTransferService
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly CustomerDomainPricing $pricing) {}

    public function quote(User $user, string $domain): array
    {
        $domain = strtolower(trim($domain));
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.com$/', $domain)) {
            throw new CheckoutUnavailable('Enter a valid .com domain.');
        }
        if (Domain::where('name', $domain)->exists()) {
            throw new CheckoutUnavailable('This domain is already managed on the platform.');
        }
        if ($user->transfers()->where('domain', $domain)->whereIn('status', ['awaiting_payment', 'paid', 'pending', 'processing', 'action_required', 'ambiguous'])->exists()) {
            throw new CheckoutUnavailable('An active transfer already exists for this domain.');
        }
        $currency = strtoupper((string) config('onlinenic.account_currency', ''));
        if ($currency === '' || $currency !== strtoupper((string) config('onlinenic.customer_billing_currency', ''))) {
            throw new CheckoutUnavailable('Transfer checkout is unavailable until billing currency is configured.');
        }
        $provider = $this->registrar->getDomainPrice(new DomainPriceQuery($domain, 'transfer', 1));

        return ['domain' => $domain, 'providerPrice' => $provider->amount, 'customerPrice' => $this->pricing->customerPrice($provider), 'currency' => $currency, 'billingData' => $user->orders()->whereNotNull('billing_data')->latest()->get()->first(fn (Order $order) => is_array($order->billing_data))?->billing_data ?? []];
    }

    public function createOrder(User $user, string $domain, PaymentBillingData $billing): Order
    {
        $quote = $this->quote($user, $domain);

        return DB::transaction(function () use ($user, $billing, $quote): Order {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (Domain::where('name', $quote['domain'])->exists() || $user->transfers()->where('domain', $quote['domain'])->whereIn('status', ['awaiting_payment', 'paid', 'pending', 'processing', 'action_required', 'ambiguous'])->exists()) {
                throw new CheckoutUnavailable('An active transfer already exists for this domain.');
            }
            $order = $user->orders()->create(['type' => 'domain_transfer', 'status' => 'awaiting_payment', 'domain' => $quote['domain'], 'tld' => 'com', 'registration_period' => 1, 'provider' => 'onlinenic', 'provider_cost' => $quote['providerPrice'], 'customer_price' => $quote['customerPrice'], 'currency' => $quote['currency'], 'premium' => false, 'registration_data' => null, 'billing_data' => $billing->toArray(), 'nameservers' => []]);
            Transfer::create(['user_id' => $user->id, 'order_id' => $order->id, 'domain' => $quote['domain'], 'tld' => 'com', 'provider' => 'onlinenic', 'direction' => 'in', 'status' => 'awaiting_payment']);

            return $order;
        });
    }
}
