<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseDomain extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'license_id',
        'activation_id',
        'domain',
        'is_primary',
        'is_active',
        'allow_subdomains',
        'max_subdomains',
        'allowed_subdomains',
        'validation_token',
        'validated_at',
        'activated_at',
        'verified_at',
        'deactivated_at',
        'deactivated_by',
        'validation_method',
        'dns_record_type',
        'dns_record_value',
        'dns_validated_at',
        'last_check_in',
        'next_check_in',
        'failed_checks',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'verified_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'allow_subdomains' => 'boolean',
        'allowed_subdomains' => 'array',
        'validated_at' => 'datetime',
        'dns_validated_at' => 'datetime',
        'last_check_in' => 'datetime',
        'next_check_in' => 'datetime',
        'failed_checks' => 'integer',
        'max_subdomains' => 'integer'
    ];

    public function licenseActivation(): BelongsTo
    {
        return $this->belongsTo(LicenseActivation::class, 'activation_id');
    }

    // Relationships
    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidated($query)
    {
        return $query->whereNotNull('validated_at');
    }

    public function scopeRequiresCheckIn($query)
    {
        return $query->where('next_check_in', '<=', Carbon::now())
                    ->where('is_active', true);
    }

    // Methods
    public function isValidDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        
        if ($this->domain === $domain) {
            return true;
        }

        if ($this->allow_subdomains) {
            $pattern = preg_quote('.' . $this->domain, '/');
            if (preg_match('/' . $pattern . '$/', $domain)) {
                if (empty($this->allowed_subdomains)) {
                    return true;
                }
                return in_array($domain, $this->allowed_subdomains);
            }
        }

        return false;
    }

    public function generateValidationToken(): string
    {
        $this->validation_token = str_replace(['+', '/', '='], '', base64_encode(random_bytes(32)));
        $this->validation_method = 'file';
        $this->save();
        
        return $this->validation_token;
    }

    public function generateDnsRecord(): array
    {
        $this->dns_record_type = 'TXT';
        $this->dns_record_value = 'license-verify=' . str_replace(['+', '/', '='], '', base64_encode(random_bytes(32)));
        $this->validation_method = 'dns';
        $this->save();
        
        return [
            'type' => $this->dns_record_type,
            'name' => '_license-verify.' . $this->domain,
            'value' => $this->dns_record_value
        ];
    }

    public function validate(): bool
    {
        try {
            $validationMethod = $this->validation_method ?? 'file';
            $isValid = false;

            if ($validationMethod === 'file') {
                $isValid = $this->validateFileMethod();
            } elseif ($validationMethod === 'dns') {
                $isValid = $this->validateDnsMethod();
            }

            if ($isValid) {
                $this->update([
                    'validated_at' => Carbon::parse('2025-02-09 14:33:19'),
                    'is_active' => true,
                    'failed_checks' => 0,
                    'updated_by' => 'maab16'
                ]);

                $this->license->logEvent('domain.validated', [
                    'domain' => $this->domain,
                    'validation_method' => $validationMethod
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Domain validation failed', [
                'domain' => $this->domain,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    protected function validateFileMethod(): bool
    {
        try {
            $response = Http::get("https://{$this->domain}/.well-known/license-verify.txt");
            return $response->successful() && trim($response->body()) === $this->validation_token;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function validateDnsMethod(): bool
    {
        try {
            $records = dns_get_record("_license-verify.{$this->domain}", DNS_TXT);
            
            foreach ($records as $record) {
                if (isset($record['txt']) && $record['txt'] === $this->dns_record_value) {
                    $this->dns_validated_at = Carbon::parse('2025-02-09 14:33:19');
                    $this->save();
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkIn(): void
    {
        $this->update([
            'last_check_in' => Carbon::parse('2025-02-09 14:33:19'),
            'next_check_in' => Carbon::parse('2025-02-09 14:33:19')->addDays(
                $this->license->product->settings['domain_check_interval'] ?? 7
            ),
            'failed_checks' => 0,
            'updated_by' => 'maab16'
        ]);

        $this->license->logEvent('domain.check_in', [
            'domain' => $this->domain,
            'check_in_time' => Carbon::parse('2025-02-09 14:33:19')->toIso8601String()
        ]);
    }

    public function incrementFailedChecks(): void
    {
        $this->increment('failed_checks');
        
        if ($this->failed_checks >= ($this->license->product->settings['max_failed_checks'] ?? 3)) {
            $this->deactivate('Exceeded maximum failed checks');
        }

        $this->license->logEvent('domain.check_failed', [
            'domain' => $this->domain,
            'failed_checks' => $this->failed_checks
        ]);
    }

    public function deactivate(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'updated_by' => 'maab16'
        ]);

        // Deactivate related activations
        $this->activations()->update([
            'is_active' => false,
            'deactivated_at' => Carbon::parse('2025-02-09 14:33:19'),
            'deactivated_by' => 'maab16',
            'deactivation_reason' => 'Domain deactivated: ' . ($reason ?? 'No reason provided'),
            'updated_by' => 'maab16'
        ]);

        $this->license->logEvent('domain.deactivated', [
            'domain' => $this->domain,
            'reason' => $reason
        ]);
    }

    public function addSubdomain(string $subdomain): bool
    {
        if (!$this->allow_subdomains || !$this->is_active) {
            return false;
        }

        $currentSubdomains = $this->allowed_subdomains ?? [];
        
        if (count($currentSubdomains) >= $this->max_subdomains) {
            return false;
        }

        if (!in_array($subdomain, $currentSubdomains)) {
            $currentSubdomains[] = $subdomain;
            $this->update([
                'allowed_subdomains' => $currentSubdomains,
                'updated_by' => 'maab16'
            ]);

            $this->license->logEvent('domain.subdomain_added', [
                'domain' => $this->domain,
                'subdomain' => $subdomain
            ]);
        }

        return true;
    }

    public function removeSubdomain(string $subdomain): bool
    {
        $currentSubdomains = $this->allowed_subdomains ?? [];
        
        if (($key = array_search($subdomain, $currentSubdomains)) !== false) {
            unset($currentSubdomains[$key]);
            
            $this->update([
                'allowed_subdomains' => array_values($currentSubdomains),
                'updated_by' => 'maab16'
            ]);

            $this->license->logEvent('domain.subdomain_removed', [
                'domain' => $this->domain,
                'subdomain' => $subdomain
            ]);

            return true;
        }

        return false;
    }
}