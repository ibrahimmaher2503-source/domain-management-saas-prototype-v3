<?php

namespace App\Http\Controllers;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Domains\Services\DomainRenewalQuoteService;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class DomainRenewalController extends Controller
{
    public function store(Request $request, Domain $domain, DomainRenewalQuoteService $renewals): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        $rules = ['period' => ['required', 'integer', 'min:1', 'max:10'], 'payment_billing' => ['required', 'array']];
        foreach (['first_name', 'last_name', 'city', 'street', 'state'] as $field) {
            $rules["payment_billing.{$field}"] = ['required', 'string', 'max:255'];
        }
        $rules['payment_billing.email'] = ['required', 'email', 'max:255'];
        $rules['payment_billing.phone_number'] = ['required', 'string', 'max:40'];
        $rules['payment_billing.country'] = ['required', 'string', 'size:2'];
        $rules['payment_billing.postal_code'] = ['required', 'string', 'max:40'];
        $data = $request->validate($rules);
        $billing = $data['payment_billing'];
        try {
            $order = $renewals->createOrder($request->user(), $domain, (int) $data['period'], new PaymentBillingData(
                $billing['first_name'], $billing['last_name'], $billing['email'], $billing['phone_number'], strtoupper($billing['country']),
                $billing['city'], $billing['street'], $billing['state'], $billing['postal_code'],
            ));
        } catch (CheckoutUnavailable|InvalidArgumentException|OnlineNicException $exception) {
            return back()->withErrors(['renewal' => $exception instanceof OnlineNicException ? 'Renewal checkout is temporarily unavailable.' : $exception->getMessage()]);
        }

        return redirect()->route('orders.show', $order);
    }
}
