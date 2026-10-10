<?php

namespace App\Rules\Tenant;

use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in person may work at this branch (see User::workableStoreIds()).
 * Without it a cashier could sell, open a till or record money as any branch.
 */
class AssignedStore implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $user = Auth::user();

        if (! $user instanceof User || $user->canWorkAt((int) $value)) {
            return;
        }

        $name = Store::query()->whereKey($value)->value('name') ?? 'this branch';
        $fail("You aren't set up to work at {$name}. Ask the owner to add you to this branch.");
    }
}
