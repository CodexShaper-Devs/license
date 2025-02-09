<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseActivation extends Model
{
    use SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'license_id',
        'device_identifier',
        'device_name',
        'hardware_hash',
        'domain',
        'ip_address',
        'is_active',
        'metadata',
        'activated_at',
        'activated_by',
        'last_check_in',
        'next_check_in',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason'
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