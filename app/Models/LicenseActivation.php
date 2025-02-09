<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    use HasUuid;

    protected $fillable = [
        'license_id',
        'device_identifier',
        'device_name',
        'hardware_hash',
        'domain',
        'ip_address',
        'metadata',
        'is_active',
        'activated_at',
        'activated_by',
        'last_check_in',
        'next_check_in',
        'deactivated_at',
        'deactivated_by'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'last_check_in' => 'datetime',
        'next_check_in' => 'datetime',
        'deactivated_at' => 'datetime'
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}