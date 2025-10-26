<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'business_types';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships

    public function categories()
    {
        return $this->hasMany(BusinessCategory::class);
    }

    public function activeCategories()
    {
        return $this->categories()->where('is_active', true);
    }

    public function businesses()
    {
        return $this->hasMany(BusinessDetail::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
