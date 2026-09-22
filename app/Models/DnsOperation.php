<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dns_zone_id', 'operation', 'record_id', 'record_type', 'record_name', 'status', 'request_data'])]
class DnsOperation extends Model
{
    protected function casts(): array
    {
        return ['request_data' => 'array'];
    }
}
