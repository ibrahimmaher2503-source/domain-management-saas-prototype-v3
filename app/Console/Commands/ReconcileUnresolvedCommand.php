<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileDnsZone;
use App\Jobs\ReconcileDomainRegistration;
use App\Jobs\ReconcileDomainRenewal;
use App\Jobs\ReconcileDomainTransfer;
use App\Jobs\ReconcileSslCertificate;
use App\Models\DnsOperation;
use App\Models\RegistrarOperation;
use App\Models\SslCertificate;
use App\Models\Transfer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class ReconcileUnresolvedCommand extends Command
{
    protected $signature = 'app:reconcile-unresolved {--limit=10}';

    protected $description = 'Queue bounded read-only reconciliation for unresolved provider operations';

    public function handle(): int
    {
        $limit = min(50, max(1, (int) $this->option('limit')));
        $stale = now()->subMinutes(15);
        $operations = RegistrarOperation::query()->whereIn('operation', ['domain_registration', 'domain_renewal'])->where(fn (Builder $query) => $query->whereIn('status', ['ambiguous', 'action_required'])->orWhere(fn (Builder $pending) => $pending->where('status', 'pending')->where('started_at', '<=', $stale)))->oldest('started_at')->limit($limit)->get();
        foreach ($operations as $operation) {
            ($operation->operation === 'domain_registration' ? ReconcileDomainRegistration::class : ReconcileDomainRenewal::class)::dispatch($operation->id);
        }

        $transfers = Transfer::query()->where(fn (Builder $query) => $query->whereIn('status', ['ambiguous', 'action_required'])->orWhere(fn (Builder $pending) => $pending->whereIn('status', ['pending', 'processing'])->where('updated_at', '<=', $stale)))->oldest('updated_at')->limit($limit)->get();
        foreach ($transfers as $transfer) {
            ReconcileDomainTransfer::dispatch($transfer->id);
        }

        $certificates = SslCertificate::query()->whereNotNull('provider_order_id')->where(fn (Builder $query) => $query->whereIn('status', ['ambiguous', 'action_required'])->orWhere(fn (Builder $pending) => $pending->whereIn('status', ['pending_validation', 'processing'])->where('updated_at', '<=', $stale)))->oldest('updated_at')->limit($limit)->get();
        foreach ($certificates as $certificate) {
            ReconcileSslCertificate::dispatch($certificate->id);
        }

        $zones = DnsOperation::query()->where(fn (Builder $query) => $query->where('status', 'ambiguous')->orWhere(fn (Builder $pending) => $pending->where('status', 'pending')->where('created_at', '<=', $stale)))->distinct()->limit($limit)->pluck('dns_zone_id');
        foreach ($zones as $zoneId) {
            ReconcileDnsZone::dispatch((int) $zoneId);
        }

        $this->info('Queued '.($operations->count() + $transfers->count() + $certificates->count() + $zones->count()).' read-only reconciliation jobs.');

        return self::SUCCESS;
    }
}
