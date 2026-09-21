<?php

namespace App\Http\Controllers;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\DTOs\DomainRegistrationCheckoutData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Domains\Services\DomainCheckoutService;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class DomainCheckoutController extends Controller
{
    public function __construct(private readonly DomainCheckoutService $checkout) {}

    public function create(Request $request): Response
    {
        try {
            $quote = $this->checkout->quote((string) $request->query('domain'), (int) $request->query('period', 1));

            return Inertia::render('Checkout/Domain', ['quote' => $quote, 'error' => null]);
        } catch (CheckoutUnavailable|InvalidArgumentException|OnlineNicException $exception) {
            return Inertia::render('Checkout/Domain', ['quote' => null, 'error' => $this->safeMessage($exception)]);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'domain' => ['required', 'string'], 'period' => ['required', 'integer'],
            'payment_billing' => ['required', 'array'],
            'nameservers' => ['required', 'array'], 'nameservers.*' => ['required', 'string'],
        ];
        foreach (['first_name', 'last_name', 'city', 'street', 'state'] as $field) {
            $rules["payment_billing.{$field}"] = ['required', 'string', 'max:255'];
        }
        $rules['payment_billing.email'] = ['required', 'email', 'max:255'];
        $rules['payment_billing.phone_number'] = ['required', 'string', 'max:40'];
        $rules['payment_billing.country'] = ['required', 'string', 'size:2'];
        $rules['payment_billing.postal_code'] = ['required', 'string', 'max:40'];
        $validated = $request->validate($rules);

        try {
            $order = $this->checkout->createOrder($request->user(), new DomainRegistrationCheckoutData(
                (string) $validated['domain'], (int) $validated['period'],
                new PaymentBillingData(
                    (string) $validated['payment_billing']['first_name'],
                    (string) $validated['payment_billing']['last_name'],
                    (string) $validated['payment_billing']['email'],
                    (string) $validated['payment_billing']['phone_number'],
                    strtoupper((string) $validated['payment_billing']['country']),
                    (string) $validated['payment_billing']['city'],
                    (string) $validated['payment_billing']['street'],
                    (string) $validated['payment_billing']['state'],
                    (string) $validated['payment_billing']['postal_code'],
                ),
                array_values($validated['nameservers']),
            ));
        } catch (CheckoutUnavailable|InvalidArgumentException|OnlineNicException $exception) {
            return back()->withErrors(['checkout' => $this->safeMessage($exception)]);
        }

        return redirect()->route('orders.show', $order);
    }

    private function safeMessage(\Throwable $exception): string
    {
        return $exception instanceof OnlineNicException ? 'Domain checkout is temporarily unavailable. Please try again.' : $exception->getMessage();
    }
}
