<?php

namespace App\Jobs;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Domain\Registrar\DTOs\CheckDomainData;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Models\Domain;
use App\Models\Order;
use App\Models\RegistrarOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ProvisionDomainRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(RegistrarGateway $registrar, OnlineNicTransactionIdGenerator $transactions): void
    {
        $order = DB::transaction(function (): ?Order {
            $order = Order::query()->with('payments')->whereKey($this->orderId)->lockForUpdate()->first();
            if (! $order || $order->type !== 'domain_registration' || $order->provider !== 'onlinenic' || $order->tld !== 'com' || ! str_ends_with(strtolower($order->domain), '.com') || $order->registration_period < 1 || $order->registration_period > 10 || $order->status === 'completed' || $order->domains()->exists() || Domain::where('name', $order->domain)->exists()) {
                return null;
            }
            if ($order->status === 'provisioning' && $order->registrarOperations()->whereIn('status', ['pending', 'ambiguous'])->exists()) {
                return null;
            }
            if ($order->status !== 'paid' || ! $order->payments()->where('status', 'paid')->exists()) {
                return null;
            }
            $order->update(['status' => 'provisioning']);

            return $order->fresh();
        });
        if (! $order) {
            return;
        }

        $contactIds = [
            'registrant' => trim((string) config('onlinenic.registrant_contact_id')),
            'administrative' => trim((string) config('onlinenic.admin_contact_id')),
            'technical' => trim((string) config('onlinenic.tech_contact_id')),
            'billing' => trim((string) config('onlinenic.billing_contact_id')),
        ];
        foreach ($contactIds as $id) {
            if ($id === '' || strlen($id) > 16) {
                $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Registration requires a platform configuration review.']);

                return;
            }
        }

        try {
            $availability = $registrar->checkDomain(new CheckDomainData($order->domain));
        } catch (OnlineNicException) {
            return;
        }
        if (! $availability->available || $availability->premium || $availability->trademarkClaimRequired) {
            $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Domain became unavailable before registration.']);

            return;
        }

        $password = $order->domain_password ?: bin2hex(random_bytes(8));
        if (! $order->domain_password) {
            $order->update(['domain_password' => $password]);
        }
        $operation = $this->operation($order, 'domain_registration', $transactions->generate(), ['domain' => $order->domain, 'period' => $order->registration_period, 'nameserver_count' => count($order->nameservers)]);
        try {
            $result = $registrar->registerDomain(new DomainRegistrationData($order->domain, $order->registration_period, $order->nameservers, $contactIds, $password), $operation->cltrid);
        } catch (ProviderAmbiguousResponse) {
            $operation->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            ReconcileDomainRegistration::dispatch($operation->id)->onConnection('database')->delay(now()->addMinute());

            return;
        } catch (OnlineNicException $exception) {
            $operation->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage ?: 'Provider rejected domain registration.', 'completed_at' => now()]);
            $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Payment was successful, but the domain could not be registered.']);

            return;
        }

        $operation->update(['status' => 'completed', 'svtrid' => $result->svtrid, 'provider_code' => $result->providerCode, 'provider_message' => $result->providerMessage, 'completed_at' => now(), 'provider_metadata' => ['domain' => $result->domain, 'registered_at' => $result->registeredAt, 'expires_at' => $result->expiresAt]]);
        $info = null;
        try {
            $info = $registrar->getDomainInfo($order->domain);
        } catch (OnlineNicException) { /* confirmed registration remains valid */
        }
        $domain = Domain::firstOrCreate(['name' => $order->domain], ['user_id' => $order->user_id, 'order_id' => $order->id, 'tld' => $order->tld, 'provider' => 'onlinenic', 'status' => 'active', 'registered_at' => $this->date($info?->registeredAt ?: $result->registeredAt), 'expires_at' => $this->date($info?->expiresAt ?: $result->expiresAt), 'nameservers' => $info?->nameservers ?: $order->nameservers, 'provider_status' => $info?->providerStatus]);
        if ($domain->order_id !== $order->id || $domain->user_id !== $order->user_id) {
            $operation->update(['status' => 'ambiguous', 'provider_message' => 'Local domain ownership conflict requires review.']);

            return;
        }
        $operation->update(['domain_id' => $domain->id]);
        $order->update(['status' => 'completed']);
    }

    private function operation(Order $order, string $name, string $cltrid, array $metadata): RegistrarOperation
    {
        return $order->registrarOperations()->create(['user_id' => $order->user_id, 'provider' => 'onlinenic', 'operation' => $name, 'cltrid' => $cltrid, 'status' => 'pending', 'safe_request_metadata' => $metadata, 'started_at' => now()]);
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
