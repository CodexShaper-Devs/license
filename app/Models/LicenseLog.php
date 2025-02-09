<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LicenseLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'license_id',
        'event_type',
        'event_data',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'event_data' => 'array'
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}