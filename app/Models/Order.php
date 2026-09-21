<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'type', 'status', 'domain', 'tld', 'registration_period', 'provider', 'provider_cost', 'customer_price', 'currency', 'premium', 'tmch_lookup_key', 'registration_data', 'nameservers'])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'provider_cost' => 'decimal:2',
            'customer_price' => 'decimal:2',
            'premium' => 'boolean',
            'registration_data' => 'encrypted:array',
            'nameservers' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
