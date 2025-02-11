<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnvatoPurchase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_code',
        'buyer_username',
        'buyer_email',
        'item_id',
        'item_name',
        'purchase_date',
        'license_type',
        'support_expiry',
        'purchase_count',
        'last_verified_at',
        'last_verified_by',
        'metadata',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'support_expiry' => 'datetime',
        'last_verified_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function license(): HasOne
    {
        return $this->hasOne(License::class, 'metadata->envato_purchase_code', 'purchase_code');
    }
}