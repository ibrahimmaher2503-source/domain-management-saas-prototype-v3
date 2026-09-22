<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'domain_id', 'order_id', 'provider', 'provider_order_id', 'product_key', 'provider_product', 'status', 'provider_status', 'validation_type', 'approver_email', 'issued_at', 'expires_at', 'provider_synced_at'])]
class SslCertificate extends Model
{
    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'date', 'provider_synced_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
