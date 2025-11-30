<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessCategory extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'business_categories';

    protected $fillable = [
        'business_type_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'business_type_id' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    public function businessDetails()
    {
        return $this->hasMany(BusinessDetail::class, 'business_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, int $typeId)
    {
        return $query->where('business_type_id', $typeId);
    }
}
