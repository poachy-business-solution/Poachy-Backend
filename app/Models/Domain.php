<?php

namespace App\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'domains';

    protected $fillable = [
        'domain',
        'tenant_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean'
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(config('tenancy.tenant_model'));
    }
}
