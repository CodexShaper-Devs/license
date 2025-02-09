<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class License extends Model
{
    use HasUuid;

    protected $fillable = [
        'key',
        'user_id',
        'product_id',
        'type',
        'status',
        'seats',
        'valid_from',
        'valid_until',
        'features',
        'restrictions',
        'metadata',
        'settings',
        'signature',
        'encryption_key_id',
        'auth_key_id',
        'created_by'
    ];

    protected $casts = [
        'features' => 'array',
        'restrictions' => 'array',
        'metadata' => 'array',
        'settings' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime'
    ];

    public function user()
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