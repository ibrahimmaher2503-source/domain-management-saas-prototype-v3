<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'order_id', 'name', 'tld', 'provider', 'status', 'registered_at', 'expires_at', 'auto_renew', 'transfer_locked', 'privacy_status', 'nameservers', 'provider_status'])]
class Domain extends Model
{
    protected function casts(): array
    {
        return ['registered_at' => 'date', 'expires_at' => 'date', 'auto_renew' => 'boolean', 'transfer_locked' => 'boolean', 'nameservers' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function registrarOperations(): HasMany
    {
        return $this->hasMany(RegistrarOperation::class);
    }
}
