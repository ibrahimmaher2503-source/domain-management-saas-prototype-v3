<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['domain_id', 'provider', 'provider_zone_id', 'creation_attempted', 'status', 'provider_status', 'assigned_nameservers', 'provider_synced_at'])]
class DnsZone extends Model
{
    protected function casts(): array
    {
        return ['creation_attempted' => 'boolean', 'assigned_nameservers' => 'array', 'provider_synced_at' => 'datetime'];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(DnsOperation::class);
    }
}
