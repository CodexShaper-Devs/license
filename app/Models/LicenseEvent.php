<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LicenseEvent extends Model
{
    use HasUuids;

    public $timestamps = false;
    
    protected $fillable = [
        'license_id',
        'activation_id',
        'domain_id',
        'event_type',
        'event_data',
        'previous_state',
        'current_state',
        'changes',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_metadata',
        'country_code',
        'country_name',
        'city',
        'timezone',
        'severity',
        'success',
        'error_code',
        'error_message',
        'attempt_count',
        'response_time_ms',
        'performance_metrics',
        'security_context',
        'is_suspicious',
        'security_notes',
        'created_by',
        'created_at',
        'source',
        'environment'
    ];

    protected $casts = [
        'event_data' => 'array',
        'previous_state' => 'array',
        'current_state' => 'array',
        'changes' => 'array',
        'request_headers' => 'array',
        'request_metadata' => 'array',
        'performance_metrics' => 'array',
        'security_context' => 'array',
        'is_suspicious' => 'boolean',
        'success' => 'boolean',
        'created_at' => 'datetime',
        'attempt_count' => 'integer',
        'response_time_ms' => 'integer'
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function activation()
    {
        return $this->belongsTo(LicenseActivation::class);
    }

    public function domain()
    {
        return $this->belongsTo(LicenseDomain::class);
    }

    public static function log(
        string $eventType,
        License $license,
        array $eventData = [],
        bool $success = true,
        string $severity = 'info'
    ): self {
        return self::create([
            'license_id' => $license->id,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_headers' => request()->headers->all(),
            'success' => $success,
            'severity' => $severity,
            'created_by' => 'maab16',
            'created_at' => '2025-02-09 14:27:02'
        ]);
    }
}