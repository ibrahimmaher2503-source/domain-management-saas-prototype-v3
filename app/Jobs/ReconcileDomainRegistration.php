<?php

namespace App\Jobs;

use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReconcileDomainRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 75;

    public function __construct(public readonly int $operationId) {}

    public function handle(RegistrarGateway $registrar): void
    {
        $operation = RegistrarOperation::query()->with('order')->find($this->operationId);
        if (! $operation || ! in_array($operation->status, ['pending', 'ambiguous', 'action_required', 'completed'], true) || $operation->operation !== 'domain_registration' || ! $operation->order || $operation->order->status !== 'provisioning' || $operation->order->domains()->exists()) {
            return;
        }
        try {
            $info = $registrar->getDomainInfo($operation->order->domain);
        } catch (OnlineNicException) {
            return;
        }
        DB::transaction(function () use ($operation, $info): void {
            $order = $operation->order->fresh();
            $domain = Domain::firstOrCreate(['name' => $order->domain], ['user_id' => $order->user_id, 'order_id' => $order->id, 'tld' => $order->tld, 'provider' => 'onlinenic', 'status' => 'active', 'registered_at' => $this->date($info->registeredAt), 'expires_at' => $this->date($info->expiresAt), 'nameservers' => $info->nameservers ?: $order->nameservers, 'provider_status' => $info->providerStatus]);
            if ($domain->order_id !== $order->id || $domain->user_id !== $order->user_id) {
                return;
            }
            $operation->update(['status' => 'completed', 'completed_at' => now(), 'domain_id' => $domain->id, 'svtrid' => $info->svtrid, 'provider_code' => $info->providerCode, 'provider_message' => $info->providerMessage]);
            $order->update(['status' => 'completed']);
        });
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
