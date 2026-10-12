<?php

namespace App\Support;

use Illuminate\Http\Request;

/** The Poachy staff member an internal admin API call acts for (set by the InternalAdmin middleware). */
final class ActingAdmin
{
    public const ATTRIBUTE = 'acting_admin';

    public function __construct(public readonly string $id, public readonly string $role, public readonly ?string $name) {}

    public static function from(Request $request): self
    {
        $admin = $request->attributes->get(self::ATTRIBUTE);
        if (! $admin instanceof self) {
            throw new \LogicException('No acting admin on this request.');
        }

        return $admin;
    }

    /** For created_by / initiated_by columns. */
    public function tag(): string
    {
        return 'admin:'.$this->id;
    }
}
