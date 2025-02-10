<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Product extends Model
{
    use HasUuids, SoftDeletes;

    const SOURCE_CUSTOM = 'custom';
    const SOURCE_ENVATO = 'envato';
    const SOURCE_OTHER = 'other';

    const TYPE_SOFTWARE = 'software';
    const TYPE_PLUGIN = 'plugin';
    const TYPE_THEME = 'theme';
    const TYPE_SERVICE = 'service';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'type',
        'source',
        'source_product_id',
        'source_metadata',
        'requirements',
        'compatibility',
        'requires_domain_verification',
        'requires_hardware_verification',
        'max_hardware_changes',
        'check_in_interval_days',
        'offline_grace_period_days',
        'current_version',
        'version_history',
        'support_email',
        'support_url',
        'support_ends_at',
        'settings',
        'metadata',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'features' => 'array',
        'source_metadata' => 'array',
        'requirements' => 'array',
        'compatibility' => 'array',
        'version_history' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
        'support_ends_at' => 'datetime',
        'requires_domain_verification' => 'boolean',
        'requires_hardware_verification' => 'boolean',
        'max_hardware_changes' => 'integer',
        'check_in_interval_days' => 'integer',
        'offline_grace_period_days' => 'integer'
    ];

    // Relationships
    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function plans()
    {
        return $this->hasMany(LicensePlan::class);
    }

    // Methods
    public function isSupported(): bool
    {
        if (!$this->support_ends_at) {
            return true;
        }
        return $this->support_ends_at > Carbon::parse('2025-02-09 15:00:03');
    }

    public function validateSystemRequirements(array $systemInfo): array
    {
        $validation = ['valid' => true, 'issues' => []];
        
        foreach ($this->requirements as $requirement => $minVersion) {
            if (!isset($systemInfo[$requirement])) {
                $validation['valid'] = false;
                $validation['issues'][] = "Missing {$requirement}";
                continue;
            }
            
            if (version_compare($systemInfo[$requirement], $minVersion, '<')) {
                $validation['valid'] = false;
                $validation['issues'][] = "{$requirement} version {$systemInfo[$requirement]} is below minimum required version {$minVersion}";
            }
        }
        
        return $validation;
    }

    public function validateHardwareChanges(string $hardwareHash, array $history): bool
    {
        if (!$this->requires_hardware_verification) {
            return true;
        }

        $changes = 0;
        foreach ($history as $record) {
            if ($record['hash'] !== $hardwareHash) {
                $changes++;
            }
        }

        return $changes <= $this->max_hardware_changes;
    }

    public function getNextCheckInDate(): Carbon
    {
        return Carbon::parse('2025-02-09 15:00:03')->addDays($this->check_in_interval_days);
    }

    public function getGracePeriodEnd(): Carbon
    {
        return Carbon::parse('2025-02-09 15:00:03')->addDays($this->offline_grace_period_days);
    }

    public function shouldVerifyDomain(): bool
    {
        return $this->requires_domain_verification && $this->is_active;
    }
}