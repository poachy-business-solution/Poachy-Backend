<?php

namespace App\Services\Central\Customer;

use App\Helpers\PhoneNumberNormalizer;
use App\Models\CustomerAddress;
use App\Models\MarketplaceCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerProfileService
{
    // Disk used for all central (non-tenant) customer assets
    private const DISK           = 'public';
    private const AVATAR_DIR     = 'marketplace/customers/avatars';
    
    // =========================================================================
    // Profile
    // =========================================================================

    /**
     * Update the customer's profile.
     *
     * Fields that belong to User (name, email) are written there.
     * All other fields are written to MarketplaceCustomer.
     * Phone is normalised before save and uniqueness is re-validated in the request.
     *
     * Returns the refreshed customer with user loaded.
     */
    public function updateProfile(User $user, array $data): MarketplaceCustomer
    {
        return DB::connection('central')->transaction(function () use ($user, $data) {
            $customer = $user->marketplaceCustomer;

            // ── User-level fields ────────────────────────────────────────────
            $userFields = array_filter([
                'name'  => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($userFields)) {
                // If email changed, require re-verification
                if (isset($userFields['email']) && $userFields['email'] !== $user->email) {
                    $userFields['email_verified_at'] = null;
                }
                $user->update($userFields);
            }

            // ── Customer-level fields ────────────────────────────────────────
            $customerFields = array_filter([
                'phone'             => isset($data['phone'])
                                        ? PhoneNumberNormalizer::normalize($data['phone'])
                                        : null,
                'date_of_birth'     => $data['date_of_birth'] ?? null,
                'gender'            => $data['gender'] ?? null,
                'accepts_marketing' => $data['accepts_marketing'] ?? null,
                'accepts_sms'       => $data['accepts_sms'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($customerFields)) {
                // If phone changed, require re-verification
                if (isset($customerFields['phone']) &&
                    $customerFields['phone'] !== $customer->phone) {
                    $customerFields['phone_verified']    = false;
                    $customerFields['phone_verified_at'] = null;
                }
                $customer->update($customerFields);
            }

            return $customer->fresh()->load('user');
        });
    }

    /**
     * Store a new profile picture, deleting the previous one if it exists.
     *
     * Storage path: storage/app/public/marketplace/customers/avatars/{uuid}.{ext}
     * Public URL:   /storage/marketplace/customers/avatars/{uuid}.{ext}
     *
     * Returns the customer with user loaded so the resource can be returned immediately.
     */
    public function updateProfilePicture(User $user, UploadedFile $file): MarketplaceCustomer
    {
        $customer = $user->marketplaceCustomer;

        // Delete the old file from disk if one exists
        if ($customer->profile_picture) {
            $oldPath = $this->pathFromUrl($customer->profile_picture);
            if ($oldPath && Storage::disk(self::DISK)->exists($oldPath)) {
                Storage::disk(self::DISK)->delete($oldPath);
            }
        }

        // Store the new file under a UUID-based name to prevent enumeration
        $path = $file->storeAs(
            self::AVATAR_DIR,
            sprintf('%s.%s', (string) str()->uuid(), $file->extension()),
            self::DISK,
        );

        // Persist the publicly accessible URL (not the raw storage path)
        $customer->update([
            'profile_picture' => Storage::disk(self::DISK)->url($path),
        ]);

        return $customer->fresh()->load('user');
    }

    // =========================================================================
    // Delivery Addresses
    // =========================================================================

    /**
     * Return all active delivery addresses for the authenticated customer.
     * Default address is always listed first.
     */
    public function getAddresses(MarketplaceCustomer $customer): Collection
    {
        return $customer->addresses()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Create a new delivery address.
     * If this is flagged as default, demote all other addresses first.
     */
    public function createAddress(MarketplaceCustomer $customer, array $data): CustomerAddress
    {
        return DB::connection('central')->transaction(function () use ($customer, $data) {
            if (!empty($data['is_default'])) {
                $this->clearDefaultFlag($customer->id);
            }

            // Normalise recipient phone
            if (isset($data['recipient_phone'])) {
                $data['recipient_phone'] = PhoneNumberNormalizer::normalize($data['recipient_phone']);
            }

            return $customer->addresses()->create($data);
        });
    }

    /**
     * Update an existing address that belongs to this customer.
     *
     * @throws ValidationException if the address doesn't belong to the customer
     */
    public function updateAddress(MarketplaceCustomer $customer, int $addressId, array $data): CustomerAddress
    {
        $address = $this->findOwnedAddress($customer, $addressId);

        return DB::connection('central')->transaction(function () use ($customer, $address, $data) {
            if (!empty($data['is_default'])) {
                $this->clearDefaultFlag($customer->id);
            }

            if (isset($data['recipient_phone'])) {
                $data['recipient_phone'] = PhoneNumberNormalizer::normalize($data['recipient_phone']);
            }

            $address->update($data);

            return $address->fresh();
        });
    }

    /**
     * Soft-delete (deactivate) an address that belongs to this customer.
     * Prevents deletion of the only remaining default address.
     *
     * @throws ValidationException
     */
    public function deleteAddress(MarketplaceCustomer $customer, int $addressId): void
    {
        $address = $this->findOwnedAddress($customer, $addressId);

        if ($address->is_default) {
            $otherCount = $customer->addresses()
                ->where('is_active', true)
                ->where('id', '!=', $addressId)
                ->count();

            if ($otherCount === 0) {
                throw ValidationException::withMessages([
                    'address' => ['You cannot delete your only delivery address.'],
                ]);
            }

            // Promote the most-recently created remaining address to default
            $customer->addresses()
                ->where('is_active', true)
                ->where('id', '!=', $addressId)
                ->orderByDesc('created_at')
                ->first()
                ?->update(['is_default' => true]);
        }

        // $address->update(['is_active' => false]);
        $address->delete();
    }

    // -------------------------------------------------------------------------

    private function clearDefaultFlag(int $customerId): void
    {
        CustomerAddress::where('customer_id', $customerId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * Resolve an address that must belong to the given customer.
     *
     * @throws ValidationException
     */
    private function findOwnedAddress(MarketplaceCustomer $customer, int $addressId): CustomerAddress
    {
        $address = $customer->addresses()->where('is_active', true)->find($addressId);

        if (!$address) {
            throw ValidationException::withMessages([
                'address' => ['Address not found.'],
            ]);
        }

        return $address;
    }

    /**
     * Derive the disk-relative storage path from a full public URL.
     * Storage::url() prepends '/storage/', so we strip that prefix to get
     * the path Storage::disk('public') understands.
     *
     * e.g. "/storage/marketplace/customers/avatars/uuid.jpg"
     *   →  "marketplace/customers/avatars/uuid.jpg"
     */
    private function pathFromUrl(string $url): ?string
    {
        // Handles both absolute URLs and relative /storage/... paths
        $parsed = parse_url($url, PHP_URL_PATH);

        if (!$parsed) {
            return null;
        }

        // Strip the leading /storage/ segment that Laravel's public disk prepends
        return ltrim(str_replace('/storage/', '', $parsed), '/') ?: null;
    }
}