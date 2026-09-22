<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'order_id', 'name', 'tld', 'provider', 'acquisition_source', 'imported_by_admin_id', 'imported_at', 'internal_note', 'status', 'registered_at', 'expires_at', 'auto_renew', 'transfer_locked', 'privacy_status', 'nameservers', 'provider_status', 'provider_synced_at'])]
class Domain extends Model
{
    protected function casts(): array
    {
        return ['registered_at' => 'date', 'expires_at' => 'date', 'auto_renew' => 'boolean', 'transfer_locked' => 'boolean', 'nameservers' => 'array', 'provider_synced_at' => 'datetime', 'imported_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_admin_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function registrarOperations(): HasMany
    {
        return $this->hasMany(RegistrarOperation::class);
    }

    public function renewalOrders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function dnsZone(): HasOne
    {
        return $this->hasOne(DnsZone::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function sslCertificates(): HasMany
    {
        return $this->hasMany(SslCertificate::class);
    }
}
