<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'order_id', 'provider', 'status', 'amount', 'currency', 'provider_intention_id', 'provider_order_id', 'provider_transaction_id', 'provider_reference', 'provider_status', 'checkout_reference', 'paid_at', 'failed_at', 'provider_metadata'])]
class Payment extends Model
{
    protected $hidden = ['provider_metadata', 'checkout_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'provider_metadata' => 'array', 'paid_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
