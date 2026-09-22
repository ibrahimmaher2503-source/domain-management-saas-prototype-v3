<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchPaidOrderFulfillment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (! $order || $order->status !== 'paid' || ! $order->payments()->where('status', 'paid')->exists()) {
            return;
        }

        match ($order->type) {
            'domain_registration' => ProvisionDomainRegistration::dispatch($order->id)->onConnection('database'),
            'domain_renewal' => ProvisionDomainRenewal::dispatch($order->id)->onConnection('database'),
            default => $order->update(['status' => 'failed', 'provisioning_failure_reason' => 'Paid order requires review.']),
        };
    }
}
