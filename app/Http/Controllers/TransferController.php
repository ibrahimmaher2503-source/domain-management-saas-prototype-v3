<?php

namespace App\Http\Controllers;

use App\Domain\Billing\DTOs\PaymentBillingData;
use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Domains\Services\DomainTransferService;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Jobs\ReconcileDomainTransfer;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Transfers/Index', ['transfers' => $request->user()->transfers()->latest()->get()->map(fn (Transfer $transfer) => $this->data($transfer))]);
    }

    public function create(Request $request, DomainTransferService $service): Response
    {
        $quote = null;
        $error = null;
        if ($request->filled('domain')) {
            try {
                $quote = $service->quote($request->user(), (string) $request->query('domain'));
            } catch (CheckoutUnavailable|OnlineNicException $e) {
                $error = $e instanceof CheckoutUnavailable ? $e->getMessage() : 'The transfer price could not be loaded.';
            }
        }

        return Inertia::render('Transfers/Create', ['quote' => $quote, 'error' => $error]);
    }

    public function store(Request $request, DomainTransferService $service): RedirectResponse
    {
        $rules = ['domain' => ['required', 'string', 'max:253'], 'payment_billing' => ['required', 'array']];
        foreach (['first_name', 'last_name', 'city', 'street', 'state'] as $field) {
            $rules["payment_billing.$field"] = ['required', 'string', 'max:255'];
        }
        $rules['payment_billing.email'] = ['required', 'email', 'max:255'];
        $rules['payment_billing.phone_number'] = ['required', 'string', 'max:40'];
        $rules['payment_billing.country'] = ['required', 'string', 'size:2'];
        $rules['payment_billing.postal_code'] = ['required', 'string', 'max:40'];
        $data = $request->validate($rules);
        $b = $data['payment_billing'];
        try {
            $order = $service->createOrder($request->user(), $data['domain'], new PaymentBillingData($b['first_name'], $b['last_name'], $b['email'], $b['phone_number'], strtoupper($b['country']), $b['city'], $b['street'], $b['state'], $b['postal_code']));
        } catch (CheckoutUnavailable|OnlineNicException $e) {
            return back()->withErrors(['transfer' => $e instanceof CheckoutUnavailable ? $e->getMessage() : 'Transfer checkout is temporarily unavailable.']);
        }

        return redirect()->route('orders.show', $order);
    }

    public function show(Request $request, Transfer $transfer): Response
    {
        abort_unless($transfer->user_id === $request->user()->id, 404);

        $activity = $transfer->order?->registrarOperations()->whereIn('operation', ['request_registrar_transfer', 'cancel_registrar_transfer'])->latest()->get()->map(fn ($operation) => [
            'id' => $operation->id,
            'label' => $operation->operation === 'cancel_registrar_transfer' ? match ($operation->status) {
                'completed' => 'Transfer cancelled', 'failed' => 'Transfer cancellation failed', 'ambiguous' => 'Transfer awaiting confirmation', default => 'Transfer cancellation requested'
            } : match ($transfer->status) {
                'completed' => 'Transfer completed', 'failed' => 'Transfer failed', 'cancelled' => 'Transfer cancelled', 'action_required', 'ambiguous' => 'Transfer awaiting confirmation', 'pending', 'processing' => 'Transfer processing', default => 'Transfer requested'
            },
            'at' => $operation->completed_at?->toIso8601String() ?? $operation->started_at?->toIso8601String(),
        ])->values() ?? [];

        return Inertia::render('Transfers/Show', ['transfer' => $this->data($transfer), 'activity' => $activity, 'notice' => session('transfer_notice'), 'error' => session('errors')?->first('cancel')]);
    }

    public function refresh(Request $request, Transfer $transfer): RedirectResponse
    {
        abort_unless($transfer->user_id === $request->user()->id, 404);
        if (! in_array($transfer->status, ['completed', 'failed', 'cancelled'], true)) {
            ReconcileDomainTransfer::dispatch($transfer->id)->onConnection('database');
        }

        return back()->with('transfer_notice', 'Transfer status refresh queued.');
    }

    public function cancel(Request $request, Transfer $transfer, RegistrarGateway $registrar, OnlineNicTransactionIdGenerator $transactions): RedirectResponse
    {
        abort_unless($transfer->user_id === $request->user()->id, 404);
        $request->validate(['confirmed' => ['accepted']]);
        if ($transfer->direction !== 'in' || $transfer->provider !== 'onlinenic' || ! $transfer->order || ! in_array($transfer->status, ['pending', 'processing'], true)) {
            return back()->withErrors(['cancel' => 'This transfer can no longer be cancelled.']);
        }
        if ($transfer->order?->registrarOperations()->where('operation', 'cancel_registrar_transfer')->whereIn('status', ['pending', 'ambiguous', 'completed'])->exists()) {
            return back()->with('transfer_notice', 'Cancellation is already being confirmed; it was not submitted again.');
        }
        try {
            $current = $registrar->getRegistrarTransferStatus($transfer->domain);
        } catch (OnlineNicException) {
            return back()->withErrors(['cancel' => 'Current transfer state could not be confirmed.']);
        }
        $transfer->update(['provider_status' => $current->providerStatus, 'status' => $current->status, 'provider_synced_at' => now()]);
        if (! in_array($current->status, ['pending', 'processing'], true)) {
            return back()->withErrors(['cancel' => 'This transfer can no longer be cancelled.']);
        }
        $operation = $transfer->order->registrarOperations()->create(['user_id' => $transfer->user_id, 'provider' => 'onlinenic', 'operation' => 'cancel_registrar_transfer', 'cltrid' => $transactions->generate(), 'status' => 'pending', 'safe_request_metadata' => ['domain' => $transfer->domain], 'started_at' => now()]);
        try {
            $result = $registrar->cancelRegistrarTransfer($transfer->domain, $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            $transfer->update(['status' => 'ambiguous']);
            ReconcileDomainTransfer::dispatch($transfer->id)->onConnection('database')->delay(now()->addMinute());

            return back()->with('transfer_notice', 'Cancellation is awaiting registrar confirmation.');
        } catch (OnlineNicException $e) {
            $operation->update(['status' => 'failed', 'provider_code' => $e->providerCode, 'provider_message' => $e->providerMessage, 'completed_at' => now()]);

            return back()->withErrors(['cancel' => 'The registrar rejected the cancellation.']);
        }
        $operation->update(['status' => 'completed', 'svtrid' => $result->svtrid, 'provider_code' => $result->providerCode, 'completed_at' => now()]);
        $transfer->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return back()->with('transfer_notice', 'Transfer cancelled.');
    }

    private function data(Transfer $t): array
    {
        return ['id' => $t->id, 'domain_id' => $t->domain_id, 'domain' => $t->domain, 'direction' => $t->direction, 'status' => $t->status, 'requested_at' => $t->requested_at?->toIso8601String(), 'updated_at' => $t->updated_at?->toIso8601String(), 'can_cancel' => in_array($t->status, ['pending', 'processing'], true)];
    }
}
