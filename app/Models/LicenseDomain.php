<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseDomain extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'license_id',
        'domain',
        'is_primary',
        'allow_subdomains',
        'max_subdomains',
        'allowed_subdomains',
        'is_active',
        'validated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'allow_subdomains' => 'boolean',
        'is_active' => 'boolean',
        'validated_at' => 'datetime',
        'allowed_subdomains' => 'array'
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function isValidDomain(string $domain): bool
    {
        if ($this->domain === $domain) {
            return true;
        }

        if ($this->allow_subdomains && str_ends_with($domain, '.' . $this->domain)) {
            if (empty($this->allowed_subdomains)) {
                return true;
            }
            return in_array($domain, $this->allowed_subdomains);
        }

        return false;
    }
}
