<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Dns\Exceptions\DnsProviderException;
use App\Domain\Dns\Services\SyncDnsZone;
use App\Domain\Domains\Services\SyncDomainFromRegistrar;
use App\Http\Controllers\Controller;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\OnlineNicAccountService;
use App\Jobs\ReconcileDomainRegistration;
use App\Jobs\ReconcileDomainRenewal;
use App\Jobs\ReconcileDomainTransfer;
use App\Jobs\ReconcileSslCertificate;
use App\Models\AdminAuditLog;
use App\Models\DnsZone;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use App\Models\SslCertificate;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class AdminActionController extends Controller
{
    public function reconcile(Request $request, string $type, int $id, SyncDomainFromRegistrar $domains, SyncDnsZone $dns): RedirectResponse
    {
        $action = match ($type) {
            'domain' => 'domain.sync', 'dns' => 'dns.sync', 'registration' => 'registration.reconcile',
            'renewal' => 'renewal.reconcile', 'transfer' => 'transfer.reconcile', 'ssl' => 'ssl.reconcile',
            default => abort(404),
        };
        $this->audit($request, $action, $type, $id);

        try {
            match ($type) {
                'domain' => $this->syncDomain($id, $domains),
                'dns' => $this->syncDns($id, $dns),
                'registration' => $this->queueRegistrar($id, 'domain_registration', ReconcileDomainRegistration::class),
                'renewal' => $this->queueRegistrar($id, 'domain_renewal', ReconcileDomainRenewal::class),
                'transfer' => $this->queueTransfer($id),
                'ssl' => $this->queueSsl($id),
                default => abort(404),
            };

            return back()->with('success', str_contains($action, 'sync') ? 'Provider data refreshed.' : 'Reconciliation was queued.');
        } catch (OnlineNicException|DnsProviderException) {
            return back()->withErrors(['reconciliation' => 'The provider is temporarily unavailable.']);
        }
    }

    public function refreshBalance(Request $request, OnlineNicAccountService $account): RedirectResponse
    {
        $this->audit($request, 'provider.balance.refresh', 'provider', null);

        try {
            $account->balance(true);

            return back()->with('success', 'OnlineNIC balance refreshed.');
        } catch (Throwable) {
            return back()->withErrors(['balance' => 'Provider balance could not be refreshed.']);
        }
    }

    private function syncDomain(int $id, SyncDomainFromRegistrar $sync): void
    {
        $domain = Domain::findOrFail($id);
        $sync->sync($domain);

    }

    private function syncDns(int $id, SyncDnsZone $sync): void
    {
        $zone = DnsZone::findOrFail($id);
        $sync->sync($zone);

    }

    private function queueRegistrar(int $id, string $operationName, string $job): void
    {
        $operation = RegistrarOperation::where('operation', $operationName)->findOrFail($id);
        $job::dispatch($operation->id);

    }

    private function queueTransfer(int $id): void
    {
        $transfer = Transfer::findOrFail($id);
        ReconcileDomainTransfer::dispatch($transfer->id);

    }

    private function queueSsl(int $id): void
    {
        $certificate = SslCertificate::findOrFail($id);
        ReconcileSslCertificate::dispatch($certificate->id);

    }

    private function audit(Request $request, string $action, string $type, ?int $id): void
    {
        AdminAuditLog::create(['admin_user_id' => $request->user()->id, 'action' => $action, 'resource_type' => $type, 'resource_id' => $id]);
    }
}
