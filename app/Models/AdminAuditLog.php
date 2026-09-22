<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_user_id', 'action', 'resource_type', 'resource_id', 'safe_metadata'])]
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['safe_metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
