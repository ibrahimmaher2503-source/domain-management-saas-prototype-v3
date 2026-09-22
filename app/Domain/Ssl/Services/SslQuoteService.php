<?php

namespace App\Domain\Ssl\Services;

use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Ssl\Contracts\SslProvider;
use App\Models\Domain;
use App\Models\User;

final class SslQuoteService
{
    public function __construct(private readonly SslProvider $provider) {}

    public function product(string $key): array
    {
        $p = config("ssl.products.{$key}");
        if (! is_array($p) || ! ($p['enabled'] ?? false) || ! is_numeric($p['customer_price']) || ! $p['currency']) {
            throw new CheckoutUnavailable('This SSL product is unavailable.');
        }

return $p;
    }

    public function quote(User $user, Domain $domain, string $product, int $validity): array
    {
        abort_unless($domain->user_id === $user->id, 404);
        $p = $this->product($product);
        if (! in_array($validity, $p['validity_options'], true)) {
            throw new CheckoutUnavailable('This validity period is unavailable.');
        }

return ['domain' => $domain->name, 'product_key' => $product, 'product_name' => $p['name'], 'provider_product' => $p['provider_product'], 'validation_type' => $p['validation_type'], 'validity' => $validity, 'customer_price' => number_format((float) $p['customer_price'], 2, '.', ''), 'currency' => strtoupper($p['currency'])];
    }

    public function approvers(User $user, Domain $domain): array
    {
        abort_unless($domain->user_id === $user->id, 404);

        return $this->provider->getApproverEmails($domain->name);
    }

    public function parse(User $user, Domain $domain, string $product, string $csr): array
    {
        abort_unless($domain->user_id === $user->id, 404);
        $p = $this->product($product);
        $parsed = $this->provider->parseCsr($p['provider_product'], $csr);
        if (strcasecmp($parsed['domain'] ?? '', $domain->name) !== 0) {
            throw new CheckoutUnavailable('The CSR domain does not match the selected domain.');
        }

return $parsed;
    }
}
