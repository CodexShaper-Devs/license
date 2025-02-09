<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use HasUuids, SoftDeletes;

    const SOURCE_CUSTOM = 'custom';      // Your own marketplace
    const SOURCE_ENVATO = 'envato';      // Envato marketplace
    const SOURCE_OTHER = 'other';        // Other marketplaces
    
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_ONETIME = 'onetime';

    protected $fillable = [
        'uuid',
        'key',
        'type',
        'product_id',
        'user_id',
        'seats',
        'features',
        'valid_from',
        'valid_until',
        'restrictions',
        'metadata',
        'source',
        'source_purchase_code',
        'encryption_key_id',
        'auth_key_id',
        'status',
        'signature',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'features' => 'array',
        'restrictions' => 'array',
        'metadata' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime'
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array
     */
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function events()
    {
        return $this->hasMany(LicenseEvent::class);
    }

    public function logs()
    {
        return $this->hasMany(LicenseLog::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && 
               $this->valid_from <= Carbon::now() && 
               $this->valid_until >= Carbon::now();
    }

    public function hasAvailableSeats(): bool
    {
        return $this->activations()
            ->where('is_active', true)
            ->count() < $this->seats;
    }

    public function getRemainingSeats(): int
    {
        return $this->seats - $this->activations()
            ->where('is_active', true)
            ->count();
    }
}