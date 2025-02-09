<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'license_id',
        'event_type',
        'event_data',
        'ip_address',
        'user_agent',
        'created_at',
        'created_by'
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime'
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
