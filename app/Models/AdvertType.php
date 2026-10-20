<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A kind of paid placement and its current price (editable by Poachy).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string $price
 * @property int $days
 * @property bool $needs_product
 * @property bool $is_active
 */
class AdvertType extends Model
{
    protected $connection = 'central';

    protected $fillable = ['key', 'name', 'description', 'price', 'days', 'needs_product', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'days' => 'integer', 'needs_product' => 'boolean', 'is_active' => 'boolean'];
    }
}
