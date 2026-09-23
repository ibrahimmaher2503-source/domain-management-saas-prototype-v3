<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'key', 'value'])]
final class IntegrationSetting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
