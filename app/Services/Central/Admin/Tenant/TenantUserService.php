<?php

namespace App\Services\Central\Admin\Tenant;

use App\Mail\Tenant\Auth\TenantCredentialsMail;
use App\Mail\Tenant\Auth\TenantUserCredentialsMail;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class TenantUserService
{
    /**
     * Create initial tenant user (admin creates in tenant DB).
     */
    public function createTenantUser(string $tenantId, array $data): TenantUser
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Generate a secure random password
        $plainPassword = $this->generateSecurePassword();

        // Initialize tenancy context
        tenancy()->initialize($tenant);

        try {
            // Create user in tenant database
            $user = TenantUser::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'is_active' => true,
            ]);

            // Send credentials email (always send since password is auto-generated)
            if ($data['send_credentials'] ?? true) {
                $primaryDomain = $tenant->domains()->first();
                $loginUrl = $primaryDomain
                    ? 'https://' . $primaryDomain->domain . '/login'
                    : config('app.url') . '/login';

                Mail::to($user->email)->queue(
                    new TenantCredentialsMail(
                        userName: $user->name,
                        email: $user->email,
                        password: $plainPassword,
                        loginUrl: $loginUrl
                    )
                );
            }

            return $user;
        } finally {
            // Always end tenancy context
            tenancy()->end();
        }
    }

    /**
     * Get all tenant users with roles.
     */
    public function getAllUsers(int $perPage = 15)
    {
        return TenantUser::with('roles')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create new tenant user (by owner/manager).
     */
    public function createUser(array $data): TenantUser
    {
        // Set tenant guard for permissions
        // config(['permission.connection' => DB::connection()->getName()]);

        // Generate random password
        $generatedPassword = $this->generateSecurePassword();

        // Create user
        $user = TenantUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($generatedPassword),
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Assign role if provided
        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        // Send credentials email
        $this->sendCredentialsEmail($user, $generatedPassword);

        return $user->fresh('roles');
    }

    /**
     * Update tenant user.
     */
    public function updateUser(int $userId, array $data): TenantUser
    {
        $user = TenantUser::findOrFail($userId);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        $user->update($updateData);

        return $user->fresh('roles');
    }

    /**
     * Assign role to user.
     */
    public function assignRole(int $userId, string $roleName): TenantUser
    {
        // config(['permission.connection' => DB::connection()->getName()]);

        $user = TenantUser::findOrFail($userId);

        // Remove existing roles
        $user->syncRoles([]);

        // Assign new role
        $user->assignRole($roleName);

        return $user->fresh('roles');
    }

    /**
     * Delete tenant user.
     */
    public function deleteUser(int $userId): void
    {
        $user = TenantUser::findOrFail($userId);

        // Prevent deleting yourself
        if ($user->id === Auth::id()) {
            throw new \Exception('You cannot delete your own account.');
        }

        // Check if this is the last owner
        if ($user->hasRole('owner')) {
            $ownerCount = TenantUser::role('owner')->count();
            if ($ownerCount <= 1) {
                throw new \Exception('Cannot delete the last owner. Please assign another owner first.');
            }
        }

        $user->delete();
    }

    /**
     * Get available roles.
     */
    public function getRoles()
    {
        // config(['permission.connection' => DB::connection()->getName()]);

        return Role::with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('name'),
                ];
            });
    }

    /**
     * Generate a secure random password.
     */
    private function generateSecurePassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $special = '!@#$%^&*';

        $allChars = $uppercase . $lowercase . $numbers . $special;

        // Ensure at least one character from each set
        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]
            . $lowercase[random_int(0, strlen($lowercase) - 1)]
            . $numbers[random_int(0, strlen($numbers) - 1)]
            . $special[random_int(0, strlen($special) - 1)];

        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle to avoid predictable pattern
        return str_shuffle($password);
    }

    /**
     * Send credentials email to the user
     */
    private function sendCredentialsEmail(TenantUser $user, string $password): void
    {
        // Get current tenant from context
        $tenant = tenant();

        if (!$tenant) {
            throw new \Exception('Tenant context not initialized');
        }

        $primaryDomain = \App\Models\Domain::on('central')
            ->where('tenant_id', $tenant->id)
            ->orderBy('id', 'asc')
            ->first();

        $loginUrl = $primaryDomain
            ? 'https://' . $primaryDomain->domain . '/login'
            : config('app.url') . '/login';

        Mail::to($user->email)->send(
            new TenantUserCredentialsMail(
                userName: $user->name,
                email: $user->email,
                password: $password,
                loginUrl: $loginUrl,
                role: $user->roles->first()?->name ?? 'user'
            )
        );
    }
}
