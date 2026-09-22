<?php

namespace App\Domain\Ssl\Services;

use App\Domain\Domains\Exceptions\CheckoutUnavailable;
use App\Domain\Ssl\Contracts\SslProvider;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Jobs\ReconcileSslCertificate;
use App\Models\RegistrarOperation;
use App\Models\SslCertificate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class SslMaintenanceService
{
    public function __construct(private readonly SslProvider $provider, private readonly OnlineNicTransactionIdGenerator $transactions) {}

    /** @return array{cancel: bool, change_approver_email: bool, resend_approver_email: bool, reissue: bool, resend_fulfillment_email: bool} */
    public static function actions(SslCertificate $certificate): array
    {
        $pending = $certificate->status === 'pending_validation';
        $issued = $certificate->status === 'issued' && (! $certificate->expires_at || $certificate->expires_at->isFuture());

        return ['cancel' => in_array($certificate->status, ['pending_validation', 'processing'], true), 'change_approver_email' => $pending, 'resend_approver_email' => $pending, 'reissue' => $issued, 'resend_fulfillment_email' => $issued];
    }

    public function cancel(SslCertificate $certificate): void
    {
        $this->guard($certificate, 'cancel');
        $this->write($certificate, 'cancel_certificate', fn ($id) => $this->provider->cancelCertificate($certificate->provider_order_id, $id), fn () => $certificate->update(['status' => 'cancelled']), true);
    }

    public function changeApproverEmail(SslCertificate $certificate, string $email): void
    {
        $this->guard($certificate, 'change_approver_email');
        if (! in_array($email, $this->provider->getApproverEmails($certificate->domain->name), true)) {
            throw new CheckoutUnavailable('Choose an approver email returned by the provider.');
        }
        $this->write($certificate, 'change_approver_email', fn ($id) => $this->provider->changeApproverEmail($certificate->provider_order_id, $email, $id), fn () => $certificate->update(['approver_email' => $email]), true, ['new_approver_email' => $email]);
    }

    public function resendApproverEmail(SslCertificate $certificate): void
    {
        $this->guard($certificate, 'resend_approver_email');
        $this->throttle($certificate, 'resend_approver_email');
        $this->write($certificate, 'resend_approver_email', fn ($id) => $this->provider->resendApproverEmail($certificate->provider_order_id, $id), fn () => null);
    }

    public function reissue(SslCertificate $certificate, string $csr): void
    {
        $this->guard($certificate, 'reissue');
        if (str_contains($csr, 'PRIVATE KEY')) {
            throw new CheckoutUnavailable('Submit a CSR only. Private keys are not accepted.');
        }
        $parsed = $this->provider->parseCsr($certificate->provider_product, $csr);
        $domain = strtolower($certificate->domain->name);
        $commonName = strtolower($parsed['domain'] ?? '');
        $expected = str_contains(strtolower($certificate->provider_product), 'wildcard') ? '*.'.$domain : $domain;
        if ($commonName !== $expected) {
            throw new CheckoutUnavailable('The CSR common name does not match this certificate.');
        }
        $this->write($certificate, 'reissue_certificate', fn ($id) => $this->provider->reissueCertificate($certificate->provider_order_id, $csr, $id), fn () => $certificate->update(['status' => 'processing']), true);
    }

    public function resendFulfillmentEmail(SslCertificate $certificate): void
    {
        $this->guard($certificate, 'resend_fulfillment_email');
        $this->throttle($certificate, 'resend_fulfillment_email');
        $this->write($certificate, 'resend_fulfillment_email', fn ($id) => $this->provider->resendFulfillmentEmail($certificate->provider_order_id, $id), fn () => null);
    }

    private function guard(SslCertificate $certificate, string $action): void
    {
        if (! $certificate->provider_order_id || ! self::actions($certificate)[$action]) {
            throw new CheckoutUnavailable('This action is not available for the current certificate status.');
        }
        $operation = ['cancel' => 'cancel_certificate', 'reissue' => 'reissue_certificate'][$action] ?? $action;
        if ($certificate->order?->registrarOperations()->where('operation', $operation)->where('status', 'ambiguous')->exists()) {
            throw new CheckoutUnavailable('This action is awaiting provider confirmation and cannot be repeated.');
        }
    }

    private function throttle(SslCertificate $certificate, string $action): void
    {
        $key = "ssl:{$action}:{$certificate->id}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw new CheckoutUnavailable('Please wait before requesting another email.');
        }
        RateLimiter::hit($key, 600);
    }

    /** @param callable(string): void $send
     * @param  callable(): void  $confirmed
     * @param  array<string, string>  $metadata
     */
    private function write(SslCertificate $certificate, string $operation, callable $send, callable $confirmed, bool $reconcile = false, array $metadata = []): void
    {
        $op = $this->reserve($certificate, $operation, $metadata);
        try {
            $send($op->cltrid);
            $confirmed();
            $op->update(['status' => 'completed', 'provider_code' => '1000', 'completed_at' => now()]);
            if ($reconcile && $operation === 'reissue_certificate') {
                ReconcileSslCertificate::dispatch($certificate->id);
            }
        } catch (ProviderAmbiguousResponse) {
            $op->update(['status' => 'ambiguous', 'provider_message' => 'Provider response was ambiguous.']);
            if ($reconcile) {
                ReconcileSslCertificate::dispatch($certificate->id);
            }
            throw new CheckoutUnavailable('Certificate maintenance is awaiting provider confirmation.');
        } catch (OnlineNicException $exception) {
            $op->update(['status' => 'failed', 'provider_code' => $exception->providerCode, 'provider_message' => $exception->providerMessage, 'completed_at' => now()]);
            throw new CheckoutUnavailable('The provider rejected this certificate action.');
        }
    }

    /** @param array<string, string> $metadata */
    private function reserve(SslCertificate $certificate, string $operation, array $metadata): RegistrarOperation
    {
        return DB::transaction(function () use ($certificate, $operation, $metadata): RegistrarOperation {
            $locked = SslCertificate::with('order')->lockForUpdate()->findOrFail($certificate->id);
            if ($locked->order->registrarOperations()->where('operation', $operation)->whereIn('status', ['pending', 'ambiguous'])->exists()) {
                throw new CheckoutUnavailable('This action is already pending provider confirmation.');
            }

            return $locked->order->registrarOperations()->create(['user_id' => $locked->user_id, 'domain_id' => $locked->domain_id, 'provider' => $locked->provider, 'operation' => $operation, 'cltrid' => $this->transactions->generate(), 'status' => 'pending', 'safe_request_metadata' => $metadata, 'started_at' => now()]);
        });
    }
}
