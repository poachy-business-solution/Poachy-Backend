<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\ProductBrand;

class BrandDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
    ) {}

    public static function fromModel(ProductBrand $brand): self
    {
        return new self(
            id: $brand->id,
            name: $brand->name,
            slug: $brand->slug,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
