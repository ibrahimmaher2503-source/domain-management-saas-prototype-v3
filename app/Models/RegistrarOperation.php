<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'order_id', 'domain_id', 'provider', 'operation', 'cltrid', 'svtrid', 'status', 'provider_code', 'provider_message', 'safe_request_metadata', 'provider_metadata', 'started_at', 'completed_at'])]
class RegistrarOperation extends Model
{
    protected function casts(): array
    {
        return ['safe_request_metadata' => 'array', 'provider_metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
