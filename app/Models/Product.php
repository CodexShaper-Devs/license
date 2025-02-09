<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'type',
        'price',
        'is_active',
        'metadata',
        'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'settings' => 'array',
        'price' => 'decimal:2'
    ];

    public function licenses()
    {
        return $this->hasMany(License::class);
    }
}