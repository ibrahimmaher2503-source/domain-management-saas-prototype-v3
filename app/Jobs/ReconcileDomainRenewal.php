<?php

namespace App\Jobs;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\RegistrarOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReconcileDomainRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 75;

    public function __construct(public readonly int $operationId, public readonly int $attempt = 1) {}

    public function handle(RegistrarGateway $registrar): void
    {
        $operation = RegistrarOperation::query()->with(['order', 'domain'])->find($this->operationId);
        if (! $operation || ! in_array($operation->status, ['pending', 'ambiguous', 'action_required', 'completed'], true) || $operation->operation !== 'domain_renewal' || ! $operation->order || ! $operation->domain || $operation->order->status !== 'provisioning') {
            return;
        }
        try {
            $info = $registrar->getDomainInfo($operation->domain->name);
        } catch (OnlineNicException) {
            $this->again();

            return;
        }
        $before = $operation->safe_request_metadata['expires_at_before'] ?? null;
        $period = (int) ($operation->safe_request_metadata['period'] ?? 0);
        try {
            $after = is_string($info->expiresAt) && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $info->expiresAt, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? sprintf('%04d-%02d-%02d', (int) $parts[1], (int) $parts[2], (int) $parts[3]) : null;
            $confirmed = $before && $after && $period > 0 && Carbon::parse($after)->gte(Carbon::parse($before)->addYearsNoOverflow($period));
        } catch (\Throwable) {
            $confirmed = false;
        }
        if (! $confirmed) {
            $this->again();

            return;
        }

        DB::transaction(function () use ($operation, $info, $after): void {
            $operation->domain->update(['expires_at' => $after, 'provider_synced_at' => now(), 'provider_status' => $info->providerStatus ?: $operation->domain->provider_status]);
            $operation->update(['status' => 'completed', 'completed_at' => now(), 'svtrid' => $info->svtrid, 'provider_code' => $info->providerCode, 'provider_message' => $info->providerMessage]);
            $operation->order->update(['status' => 'completed', 'provisioning_failure_reason' => null]);
        });
    }

    private function again(): void
    {
        if ($this->attempt < 3) {
            self::dispatch($this->operationId, $this->attempt + 1)->delay(now()->addMinutes([2 => 5, 3 => 15][$this->attempt + 1]));
        }
    }
}
