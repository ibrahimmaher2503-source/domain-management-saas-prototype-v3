<?php

namespace App\Domain\Domains\Services;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\DTOs\DomainRenewalQuote;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Models\Domain;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DomainRenewalQuoteService
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly CustomerDomainPricing $pricing) {}

    public function quote(User $user, Domain $domain, int $period): DomainRenewalQuote
    {
        $this->assertEligible($user, $domain, $period);
        $currency = strtoupper((string) config('onlinenic.account_currency', ''));
        $customerCurrency = strtoupper((string) config('onlinenic.customer_billing_currency', ''));
        if ($currency === '' || $customerCurrency === '' || $currency !== $customerCurrency) {
            throw new CheckoutUnavailable('Renewal is temporarily unavailable until billing currency is configured.');
        }
        $providerPrice = $this->registrar->getDomainPrice(new DomainPriceQuery($domain->name, 'renewal', $period));

        return new DomainRenewalQuote($domain->name, $period, $providerPrice, $this->pricing->customerPrice($providerPrice), $currency, $domain->expires_at?->toDateString(), $this->latestBilling($user, $domain));
    }

    public function createOrder(User $user, Domain $domain, int $period, PaymentBillingData $billing): Order
    {
        $quote = $this->quote($user, $domain, $period);

        return DB::transaction(function () use ($user, $domain, $billing, $quote): Order {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertEligible($user, $locked, $quote->period);
            if ($locked->renewalOrders()->where('user_id', $user->id)->where('type', 'domain_renewal')->whereIn('status', ['awaiting_payment', 'paid', 'provisioning'])->exists()) {
                throw new CheckoutUnavailable('An active renewal order already exists for this domain.');
            }

            return $user->orders()->create([
                'domain_id' => $locked->id, 'type' => 'domain_renewal', 'status' => 'awaiting_payment', 'domain' => $quote->domain, 'tld' => 'com',
                'registration_period' => $quote->period, 'provider' => 'onlinenic', 'provider_cost' => $quote->providerPrice->amount,
                'customer_price' => $quote->customerPrice, 'currency' => $quote->currency, 'premium' => false,
                'registration_data' => null, 'billing_data' => $billing->toArray(), 'nameservers' => $locked->nameservers ?? [],
            ]);
        });
    }

    private function assertEligible(User $user, Domain $domain, int $period): void
    {
        if ($domain->user_id !== $user->id || $domain->status !== 'active' || $domain->provider !== 'onlinenic' || $domain->tld !== 'com' || ! str_ends_with(strtolower($domain->name), '.com')) {
            throw new CheckoutUnavailable('This domain is not eligible for renewal.');
        }
        if ($period < 1 || $period > 10) {
            throw new CheckoutUnavailable('Select a renewal period from 1 to 10 years.');
        }
    }

    /** @return array<string, string> */
    private function latestBilling(User $user, Domain $domain): array
    {
        $order = $user->orders()->where('domain', $domain->name)->whereNotNull('billing_data')->latest()->get()->first(fn (Order $order): bool => is_array($order->billing_data));

        return $order?->billing_data ?? [];
    }
}
