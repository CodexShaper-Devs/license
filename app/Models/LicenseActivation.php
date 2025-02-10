<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class LicenseActivation extends Model
{
    use HasUuids, SoftDeletes;

    const TYPE_DOMAIN = 'domain';
    const TYPE_MACHINE = 'machine';
    const TYPE_USER = 'user';

    protected $fillable = [
        'license_id',
        'license_domain_id',
        'activation_token',
        'type',
        'device_identifier',
        'device_name',
        'hardware_hash',
        'system_info',
        'ip_address',
        'mac_address',
        'is_active',
        'activated_at',
        'last_check_in',
        'next_check_in',
        'failed_checks',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
        'metadata',
        'user_agent',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'hardware_hash' => 'array',
        'system_info' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'last_check_in' => 'datetime',
        'next_check_in' => 'datetime',
        'deactivated_at' => 'datetime',
        'failed_checks' => 'integer'
    ];

    // Relationships
    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function domain()
    {
        return $this->belongsTo(LicenseDomain::class, 'license_domain_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequiresCheckIn($query)
    {
        return $query->where('next_check_in', '<=', Carbon::now())
                    ->where('is_active', true);
    }

    // Methods
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->next_check_in && $this->next_check_in < Carbon::now()) {
            return false;
        }

        return true;
    }

    public function checkIn(): void
    {
        $this->update([
            'last_check_in' => Carbon::now(),
            'next_check_in' => Carbon::now()->addDays(
                $this->license->product->settings['check_in_interval'] ?? 7
            ),
            'failed_checks' => 0,
            'updated_by' => 'maab16'
        ]);

        $this->license->logEvent('activation.check_in', [
            'activation_id' => $this->id,
            'device_identifier' => $this->device_identifier,
            'check_in_time' => Carbon::now()->toIso8601String()
        ]);
    }

    public function deactivate(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => Carbon::now(),
            'deactivated_by' => 'maab16',
            'deactivation_reason' => $reason,
            'updated_by' => 'maab16'
        ]);

        $this->license->logEvent('activation.deactivated', [
            'activation_id' => $this->id,
            'device_identifier' => $this->device_identifier,
            'reason' => $reason
        ]);
    }

    public function verifyHardware(array $currentHardware): bool
    {
        if (!$this->hardware_hash) {
            return true;
        }

        $matches = 0;
        $requiredMatches = count($this->hardware_hash) * 0.7; // 70% match required

        foreach ($this->hardware_hash as $key => $hash) {
            if (isset($currentHardware[$key]) && $currentHardware[$key] === $hash) {
                $matches++;
            }
        }

        return $matches >= $requiredMatches;
    }
}