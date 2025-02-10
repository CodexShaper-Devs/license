<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LicensePlan extends Model
{
    use HasUuids, SoftDeletes;

    const BILLING_YEARLY = 'yearly';
    const BILLING_LIFETIME = 'lifetime';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'billing_cycle',
        'base_seats',
        'max_seats',
        'allow_seat_upgrade',
        'price_per_seat',
        'allow_subdomains',
        'subdomains_per_seat',
        'allowed_domain_patterns',
        'allow_local_domains',
        'local_domain_patterns',
        'features',
        'settings',
        'restrictions',
        'has_trial',
        'trial_days',
        'grace_period_days',
        'is_active',
        'metadata',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'allowed_domain_patterns' => 'array',
        'local_domain_patterns' => 'array',
        'features' => 'array',
        'settings' => 'array',
        'restrictions' => 'array',
        'metadata' => 'array',
        'base_seats' => 'integer',
        'max_seats' => 'integer',
        'price_per_seat' => 'decimal:2',
        'subdomains_per_seat' => 'integer',
        'trial_days' => 'integer',
        'grace_period_days' => 'integer',
        'allow_seat_upgrade' => 'boolean',
        'allow_subdomains' => 'boolean',
        'allow_local_domains' => 'boolean',
        'has_trial' => 'boolean',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    // Methods
    public function calculatePrice(int $seats): float
    {
        return $seats * $this->price_per_seat;
    }

    public function isValidDomainPattern(string $domain): bool
    {
        if (empty($this->allowed_domain_patterns)) {
            return true;
        }

        foreach ($this->allowed_domain_patterns as $pattern) {
            if (fnmatch($pattern, $domain)) {
                return true;
            }
        }

        if ($this->allow_local_domains && $this->isLocalDomain($domain)) {
            return true;
        }

        return false;
    }

    public function isLocalDomain(string $domain): bool
    {
        if (empty($this->local_domain_patterns)) {
            return str_ends_with($domain, '.test') || 
                   str_ends_with($domain, '.local') || 
                   $domain === 'localhost';
        }

        foreach ($this->local_domain_patterns as $pattern) {
            if (fnmatch($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }

    public function canUpgradeSeats(): bool
    {
        return $this->allow_seat_upgrade && $this->max_seats > $this->base_seats;
    }

    public function getAvailableFeatures(): array
    {
        return $this->features ?? [];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getAvailableFeatures());
    }
}