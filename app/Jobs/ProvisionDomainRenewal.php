<?php

namespace App\Jobs;

use App\Domain\Domains\Services\SyncDomainFromRegistrar;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ProvisionDomainRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(RegistrarGateway $registrar, SyncDomainFromRegistrar $sync, OnlineNicTransactionIdGenerator $transactions): void
    {
        $order = DB::transaction(function (): ?Order {
            $order = Order::query()->with(['payments', 'renewalDomain'])->whereKey($this->orderId)->lockForUpdate()->first();
            $domain = $order?->renewalDomain;
            if (! $order || ! $domain || $order->type !== 'domain_renewal' || $order->status === 'completed' || $order->provider !== 'onlinenic' || $order->tld !== 'com' || $order->registration_period < 1 || $order->registration_period > 10 || $domain->user_id !== $order->user_id || strcasecmp($domain->name, $order->domain) !== 0 || $domain->provider !== 'onlinenic' || $domain->tld !== 'com' || $domain->status !== 'active') {
                return null;
            }
            if ($order->status === 'provisioning' && $order->registrarOperations()->whereIn('status', ['pending', 'ambiguous', 'completed'])->where('operation', 'domain_renewal')->exists()) {
                return null;
            }
            if ($order->status !== 'paid' || ! $order->payments()->where('status', 'paid')->exists()) {
                return null;
            }
            $order->update(['status' => 'provisioning']);

            return $order->fresh(['renewalDomain']);
        });
        if (! $order) {
            return;
        }

        $domain = $order->renewalDomain;
        try {
            $domain = $sync->sync($domain);
        } catch (OnlineNicException) { /* use the last known expiration snapshot */
        }
        $before = $domain->expires_at?->toDateString();
        $operation = $order->registrarOperations()->create([
            'user_id' => $order->user_id, 'domain_id' => $domain->id, 'provider' => 'onlinenic', 'operation' => 'domain_renewal',
            'cltrid' => $transactions->generate(), 'status' => 'pending', 'safe_request_metadata' => ['domain' => $domain->name, 'period' => $order->registration_period, 'expires_at_before' => $before], 'started_at' => now(),
        ]);
        try {
            $result = $registrar->renewDomain(new RenewDomainData($domain->name, $order->registration_period), $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            ReconcileDomainRenewal::dispatch($operation->id)->onConnection('database')->delay(now()->addMinute());

            return;
        } catch (OnlineNicException $exception) {
            $operation->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage ?: 'Provider rejected domain renewal.', 'completed_at' => now()]);
            $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Payment was successful, but the renewal could not be completed.']);

            return;
        }

        $operation->update(['status' => 'completed', 'svtrid' => $result->svtrid, 'provider_code' => $result->providerCode, 'provider_message' => $result->providerMessage, 'completed_at' => now(), 'provider_metadata' => array_filter(['domain' => $result->domain, 'expires_at' => $result->expiresAt])]);
        $confirmedExpiration = $this->date($result->expiresAt);
        if ($confirmedExpiration) {
            $domain->update(['expires_at' => $confirmedExpiration]);
        }
        try {
            $domain = $sync->sync($domain);
        } catch (OnlineNicException) { /* a confirmed write must never be repeated because a read failed */
            $domain->refresh();
        }
        if ($confirmedExpiration !== null || $this->advancedAsExpected($before, $domain->expires_at?->toDateString(), $order->registration_period)) {
            $order->update(['status' => 'completed', 'provisioning_failure_reason' => null]);
        } else {
            ReconcileDomainRenewal::dispatch($operation->id)->onConnection('database')->delay(now()->addMinute());
        }
    }

    private function date(?string $value): ?string
    {
        if (! $value || ! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $parts[1], (int) $parts[2], (int) $parts[3]);
    }

    private function advancedAsExpected(?string $before, ?string $after, int $period): bool
    {
        return $before !== null && $after !== null && Carbon::parse($after)->gte(Carbon::parse($before)->addYearsNoOverflow($period));
    }
}
