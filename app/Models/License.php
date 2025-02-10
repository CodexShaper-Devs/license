<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Services\EncryptionService;
use ParagonIE\HiddenString\HiddenString;

class License extends Model
{
    use HasUuids, SoftDeletes;

    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_LIFETIME = 'lifetime';
    const TYPE_TRIAL = 'trial';

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_TRIAL = 'trial';
    const STATUS_GRACE_PERIOD = 'grace_period';

    protected $fillable = [
        'uuid',
        'key',
        'signature',
        'encryption_key_id',
        'auth_key_id',
        'security_metadata',
        'product_id',
        'plan_id',
        'user_id',
        'type',
        'purchased_seats',
        'activated_seats',
        'seat_allocation',
        'source',
        'source_purchase_code',
        'source_metadata',
        'features',
        'restrictions',
        'valid_from',
        'valid_until',
        'trial_ends_at',
        'grace_period_ends_at',
        'last_check_in',
        'next_check_in',
        'failed_checks',
        'max_failed_checks',
        'hardware_verification_enabled',
        'hardware_changes_count',
        'hardware_history',
        'status',
        'auto_renew',
        'renewal_reminder_sent_at',
        'metadata',
        'created_by',
        'updated_by',
        'suspended_by',
        'suspension_reason'
    ];

    protected $casts = [
        'security_metadata' => 'array',
        'seat_allocation' => 'array',
        'source_metadata' => 'array',
        'features' => 'array',
        'restrictions' => 'array',
        'hardware_history' => 'array',
        'metadata' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'trial_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'last_check_in' => 'datetime',
        'next_check_in' => 'datetime',
        'renewal_reminder_sent_at' => 'datetime',
        'purchased_seats' => 'integer',
        'activated_seats' => 'integer',
        'failed_checks' => 'integer',
        'max_failed_checks' => 'integer',
        'hardware_changes_count' => 'integer',
        'hardware_verification_enabled' => 'boolean',
        'auto_renew' => 'boolean'
    ];

    public function getKeyAttribute($value)
    {
        return $value; // TEXT field, no modification needed
    }

    public function setKeyAttribute($value)
    {
        $this->attributes['key'] = $value; // TEXT field can handle long strings
    }

    public function getSignatureAttribute($value)
    {
        return $value; // TEXT field, no modification needed
    }

    public function setSignatureAttribute($value)
    {
        $this->attributes['signature'] = $value; // TEXT field can handle long strings
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function plan()
    {
        return $this->belongsTo(LicensePlan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function domains()
    {
        return $this->hasMany(LicenseDomain::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function events()
    {
        return $this->hasMany(LicenseEvent::class);
    }

    // Validation Methods
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = Carbon::parse('2025-02-09 15:00:03');

        if ($this->valid_from > $now) {
            return false;
        }

        if ($this->valid_until && $this->valid_until < $now) {
            return $this->isInGracePeriod();
        }

        if ($this->next_check_in && $this->next_check_in < $now) {
            return false;
        }

        return true;
    }

    public function isInGracePeriod(): bool
    {
        if (!$this->grace_period_ends_at) {
            return false;
        }

        return Carbon::parse('2025-02-09 15:00:03') <= $this->grace_period_ends_at;
    }

    public function hasAvailableSeats(): bool
    {
        return $this->activated_seats < $this->purchased_seats;
    }

    public function canActivateDomain(string $domain): bool
    {
        if (!$this->hasAvailableSeats()) {
            return false;
        }

        // Check if domain is already activated
        return !$this->domains()
            ->where('domain', $domain)
            ->where('is_active', true)
            ->exists();
    }

    // Security Methods
    public function verifySignature(): bool
    {
        try {
            $encryptionService = app(EncryptionService::class);
            $content = new HiddenString($this->key);
            
            return $encryptionService->verify(
                $content,
                $this->signature,
                $this->auth_key_id
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    public function verifyHardware(array $hardwareInfo): bool
    {
        if (!$this->hardware_verification_enabled) {
            return true;
        }

        $hardwareHash = $this->generateHardwareHash($hardwareInfo);
        
        return $this->product->validateHardwareChanges(
            $hardwareHash,
            $this->hardware_history ?? []
        );
    }

    // Utility Methods
    public function generateHardwareHash(array $hardwareInfo): string
    {
        $normalized = array_map('strval', $hardwareInfo);
        ksort($normalized);
        return hash('sha256', json_encode($normalized));
    }

    public function logEvent(string $event, array $data = []): void
    {
        $this->events()->create([
            'event_type' => $event,
            'event_data' => $data,
            'created_by' => 'maab16',
            'created_at' => '2025-02-09 15:00:03',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public function startGracePeriod(): void
    {
        $this->update([
            'status' => self::STATUS_GRACE_PERIOD,
            'grace_period_ends_at' => Carbon::parse('2025-02-09 15:00:03')
                ->addDays($this->plan->grace_period_days),
            'updated_by' => 'maab16'
        ]);
    }
}