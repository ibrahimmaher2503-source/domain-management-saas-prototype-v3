<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'domain_id', 'type', 'status', 'domain', 'tld', 'registration_period', 'provider', 'provider_cost', 'customer_price', 'currency', 'premium', 'tmch_lookup_key', 'registration_data', 'billing_data', 'nameservers', 'provider_contact_ids', 'domain_password', 'provisioning_failure_reason'])]
class Order extends Model
{
    protected $hidden = ['registration_data', 'billing_data', 'provider_contact_ids', 'domain_password'];

    protected function casts(): array
    {
        return [
            'provider_cost' => 'decimal:2',
            'customer_price' => 'decimal:2',
            'premium' => 'boolean',
            'registration_data' => 'encrypted:array',
            'billing_data' => 'encrypted:array',
            'nameservers' => 'array',
            'provider_contact_ids' => 'encrypted:array',
            'domain_password' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function renewalDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function registrarOperations(): HasMany
    {
        return $this->hasMany(RegistrarOperation::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function registeredDomain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }
}
