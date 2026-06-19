<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\UnitOfMeasure;

class UomDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
    ) {}

    public static function fromModel(UnitOfMeasure $uom): self
    {
        return new self(
            code: $uom->code,
            name: $uom->name,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
