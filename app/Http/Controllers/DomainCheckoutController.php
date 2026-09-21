<?php

namespace App\Http\Controllers;

use App\Domain\Domains\DTOs\DomainRegistrationCheckoutData;
use App\Domain\Domains\DTOs\RegistrationContactData;
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
            'registrant' => ['required', 'array'], 'administrative' => ['required', 'array'], 'technical' => ['required', 'array'], 'billing' => ['required', 'array'],
            'nameservers' => ['required', 'array'], 'nameservers.*' => ['required', 'string'],
        ];
        foreach (['registrant', 'administrative', 'technical', 'billing'] as $role) {
            $rules["{$role}.name"] = ['required', 'string', 'max:255'];
            $rules["{$role}.organization"] = ['nullable', 'string', 'max:255'];
            $rules["{$role}.country"] = ['required', 'string', 'size:2'];
            $rules["{$role}.province"] = ['required', 'string', 'max:255'];
            $rules["{$role}.city"] = ['required', 'string', 'max:255'];
            $rules["{$role}.street"] = ['required', 'string', 'max:255'];
            $rules["{$role}.postal_code"] = ['required', 'string', 'max:40'];
            $rules["{$role}.voice"] = ['required', 'string', 'max:40'];
            $rules["{$role}.fax"] = ['nullable', 'string', 'max:40'];
            $rules["{$role}.email"] = ['required', 'email', 'max:255'];
        }
        $validated = $request->validate($rules);

        try {
            $order = $this->checkout->createOrder($request->user(), new DomainRegistrationCheckoutData(
                (string) $validated['domain'], (int) $validated['period'],
                $this->contact($validated['registrant']), $this->contact($validated['administrative']), $this->contact($validated['technical']), $this->contact($validated['billing']),
                array_values($validated['nameservers']),
            ));
        } catch (CheckoutUnavailable|InvalidArgumentException|OnlineNicException $exception) {
            return back()->withErrors(['checkout' => $this->safeMessage($exception)]);
        }

        return redirect()->route('orders.show', $order);
    }

    /** @param array<string, string|null> $data */
    private function contact(array $data): RegistrationContactData
    {
        return new RegistrationContactData((string) $data['name'], (string) ($data['organization'] ?? ''), (string) $data['country'], (string) $data['province'], (string) $data['city'], (string) $data['street'], (string) $data['postal_code'], (string) $data['voice'], (string) ($data['fax'] ?? ''), (string) $data['email']);
    }

    private function safeMessage(\Throwable $exception): string
    {
        return $exception instanceof OnlineNicException ? 'Domain checkout is temporarily unavailable. Please try again.' : $exception->getMessage();
    }
}
