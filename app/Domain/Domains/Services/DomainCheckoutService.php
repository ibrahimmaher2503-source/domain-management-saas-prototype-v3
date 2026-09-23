<?php

namespace App\Domain\Domains\Services;

use App\Domain\Domains\DTOs\DomainCheckoutQuote;
use App\Domain\Domains\DTOs\DomainRegistrationCheckoutData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Integrations\OnlineNic\OnlineNicSettings;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DomainCheckoutService
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly DomainSearchService $search, private readonly CustomerDomainPricing $pricing, private readonly RegistrationCapabilityResolver $capabilities) {}

    public function quote(string $input, int $period): DomainCheckoutQuote
    {
        $domain = $this->search->normalize($input);
        $capability = $this->capabilities->forDomain($domain);
        if (! $capability->supportsPeriod($period)) {
            throw new CheckoutUnavailable('This registration period is not available for the selected TLD.');
        }
        $settings = app(OnlineNicSettings::class);
        $currency = (string) $settings->value('account_currency');
        $customerCurrency = (string) $settings->value('customer_billing_currency');
        if ($currency === '' || $customerCurrency === '' || strtoupper($currency) !== strtoupper($customerCurrency)) {
            throw new CheckoutUnavailable('Checkout is temporarily unavailable until billing currency is configured.');
        }

        $availability = $this->registrar->checkDomain(new CheckDomainData($domain));
        if (! $availability->available) {
            throw new CheckoutUnavailable('This domain is no longer available.');
        }
        if ($availability->premium || $availability->trademarkClaimRequired) {
            throw new CheckoutUnavailable('Premium and trademark-claim domains are not available for purchase yet.');
        }
        $providerPrice = $this->registrar->getDomainPrice(new DomainPriceQuery($domain, 'registration', $period));

        return new DomainCheckoutQuote($domain, $period, $availability, $providerPrice, $this->pricing->customerPrice($providerPrice), strtoupper($currency), $capability);
    }

    public function createOrder(User $user, DomainRegistrationCheckoutData $data): Order
    {
        $quote = $this->quote($data->domain, $data->period);
        $this->validateNameservers($data->nameservers, $quote->capability);

        return DB::transaction(function () use ($user, $data, $quote): Order {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $active = $user->orders()->where('domain', $quote->domain)->whereIn('status', ['draft', 'awaiting_payment'])->lockForUpdate()->exists();
            if ($active) {
                throw new CheckoutUnavailable('An active order already exists for this domain.');
            }

            return $user->orders()->create([
                'type' => 'domain_registration', 'status' => 'awaiting_payment', 'domain' => $quote->domain, 'tld' => $quote->capability->tld,
                'registration_period' => $quote->period, 'provider' => 'onlinenic', 'provider_cost' => $quote->providerPrice->amount,
                'customer_price' => $quote->customerPrice, 'currency' => $quote->currency, 'premium' => false,
                'tmch_lookup_key' => null, 'billing_data' => $data->paymentBillingData->toArray(), 'nameservers' => array_values(array_map(static fn (string $name): string => strtolower(trim($name)), $data->nameservers)),
            ]);
        });
    }

    /** @param array<int, string> $nameservers */
    private function validateNameservers(array $nameservers, RegistrationCapabilityPolicy $policy): void
    {
        $normalized = array_map(static fn (string $name): string => strtolower(trim($name)), $nameservers);
        if (count($normalized) < $policy->minNameservers || count($normalized) > $policy->maxNameservers || count($normalized) !== count(array_unique($normalized))) {
            throw new CheckoutUnavailable('Provide the required number of unique nameservers.');
        }
        foreach ($normalized as $nameserver) {
            if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $nameserver)) {
                throw new CheckoutUnavailable('Enter valid nameserver hostnames.');
            }
        }
    }
}
