<?php

namespace App\Observers\Tenant;

use App\Helpers\PhoneNumberNormalizer;
use App\Models\Tenant\Customer;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CustomerObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the Customer "creating" event.
     */
    public function creating(Customer $customer): void
    {
        // Generate customer number if not provided
        if (empty($customer->customer_number)) {
            $customer->customer_number = $this->generateCustomerNumber();
        }

        // Normalize phone number (safety layer - should already be normalized from request)
        if (!empty($customer->phone)) {
            $normalizedPhone = PhoneNumberNormalizer::normalize($customer->phone);

            if ($customer->phone !== $normalizedPhone) {
                $customer->phone = $normalizedPhone;
            }
        }

        // Set registered_at timestamp
        if (empty($customer->registered_at)) {
            $customer->registered_at = now();
        }

        // Initialize default values if not set
        $customer->loyalty_points = $customer->loyalty_points ?? 0;
        $customer->total_lifetime_purchases = $customer->total_lifetime_purchases ?? 0;
        $customer->total_visits = $customer->total_visits ?? 0;
        $customer->credit_limit = $customer->credit_limit ?? 0;
        $customer->current_debt = $customer->current_debt ?? 0;
        $customer->accepts_marketing = $customer->accepts_marketing ?? false;
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $customer,
                action: 'created',
                oldValues: null,
                newValues: $this->sanitizePiiForAudit($customer->toArray()),
                description: $this->generateCreationDescription($customer),
                tags: ['customer', 'profile']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create customer audit log', [
                'tenant_id' => tenant()?->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Customer "updating" event.
     */
    public function updating(Customer $customer): void
    {
        // Normalize phone number if it's being changed
        if ($customer->isDirty('phone') && !empty($customer->phone)) {
            $normalizedPhone = PhoneNumberNormalizer::normalize($customer->phone);

            if ($customer->phone !== $normalizedPhone) {
                $customer->phone = $normalizedPhone;
            }
        }
    }

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        $this->clearCache();

        try {
            // Check if critical fields changed
            $changes = $customer->getChanges();
            $criticalFields = $customer->getCriticalFields();
            $criticalChanges = array_intersect_key($changes, array_flip($criticalFields));

            if (!empty($criticalChanges)) {
                $oldValues = $customer->getOriginal();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($customer, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['customer', 'profile'];
                if (isset($criticalChanges['credit_limit']) || isset($criticalChanges['current_debt'])) {
                    $tags[] = 'credit';
                    $tags[] = 'financial';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_active'])) {
                    $tags[] = 'status_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['customer_type'])) {
                    $tags[] = 'type_change';
                }

                $this->auditService->createAudit(
                    model: $customer,
                    action: 'updated',
                    oldValues: $this->sanitizePiiForAudit(array_intersect_key($oldValues, $criticalChanges)),
                    newValues: $this->sanitizePiiForAudit($criticalChanges),
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create customer update audit log', [
                'tenant_id' => tenant()?->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $customer,
                action: 'deleted',
                oldValues: $this->sanitizePiiForAudit($customer->toArray()),
                newValues: null,
                description: $this->generateDeletionDescription($customer),
                tags: ['customer', 'profile', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create customer deletion audit log', [
                'tenant_id' => tenant()?->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Customer "restored" event.
     */
    public function restored(Customer $customer): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $customer,
                action: 'restored',
                oldValues: null,
                newValues: $this->sanitizePiiForAudit($customer->toArray()),
                description: $this->generateRestorationDescription($customer),
                tags: ['customer', 'profile']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create customer restoration audit log', [
                'tenant_id' => tenant()?->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate unique customer number
     */
    private function generateCustomerNumber(): string
    {
        $prefix = 'CUST-';
        $year = date('Y');

        // Get last customer number for this year
        $lastCustomer = Customer::withTrashed()
            ->where('customer_number', 'like', "{$prefix}{$year}-%")
            ->orderByDesc('id')
            ->first();

        if ($lastCustomer) {
            // Extract sequence number and increment
            $lastNumber = (int) substr($lastCustomer->customer_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%s-%06d', $prefix, $year, $newNumber);
    }


    /**
     * Sanitize PII (Personally Identifiable Information) for audit logs
     * Based on GDPR compliance requirements
     */
    private function sanitizePiiForAudit(array $data): array
    {
        $piiFields = ['email', 'phone', 'address', 'date_of_birth'];

        foreach ($piiFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                // Mask PII data for audit logs
                if ($field === 'email') {
                    $data[$field] = $this->maskEmail($data[$field]);
                } elseif ($field === 'phone') {
                    $data[$field] = $this->maskPhone($data[$field]);
                } else {
                    $data[$field] = '[REDACTED]';
                }
            }
        }

        return $data;
    }

    /**
     * Mask email address for audit logs
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '[REDACTED]';
        }

        $username = $parts[0];
        $domain = $parts[1];

        // Show first 2 chars and last char of username
        if (strlen($username) > 3) {
            $masked = substr($username, 0, 2) . str_repeat('*', strlen($username) - 3) . substr($username, -1);
        } else {
            $masked = substr($username, 0, 1) . str_repeat('*', strlen($username) - 1);
        }

        return $masked . '@' . $domain;
    }

    /**
     * Mask phone number for audit logs
     */
    private function maskPhone(string $phone): string
    {
        // Show last 4 digits only
        if (strlen($phone) > 4) {
            return str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
        }

        return str_repeat('*', strlen($phone));
    }

    /**
     * Clear customer-related cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'customers'])->flush();
    }

    /**
     * Generate description for customer creation
     */
    private function generateCreationDescription(Customer $customer): string
    {
        $user = Auth::user()?->name ?? 'System';
        $customerType = $customer->customer_type->label();

        return "{$user} created customer {$customer->name} ({$customer->customer_number}) as {$customerType}";
    }

    /**
     * Generate description for customer update
     */
    private function generateUpdateDescription(Customer $customer, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Name change
        if (isset($changes['name'])) {
            $oldName = $customer->getOriginal('name');
            $newName = $changes['name'];
            return "{$user} changed customer name from {$oldName} to {$newName} ({$customer->customer_number})";
        }

        // Email change
        if (isset($changes['email'])) {
            return "{$user} updated email for customer {$customer->name} ({$customer->customer_number})";
        }

        // Phone change
        if (isset($changes['phone'])) {
            return "{$user} updated phone for customer {$customer->name} ({$customer->customer_number})";
        }

        // Credit limit change
        if (isset($changes['credit_limit'])) {
            $oldLimit = number_format($customer->getOriginal('credit_limit'), 2);
            $newLimit = number_format($changes['credit_limit'], 2);
            return "{$user} changed credit limit for {$customer->name} from KES {$oldLimit} to KES {$newLimit}";
        }

        // Current debt change
        if (isset($changes['current_debt'])) {
            $oldDebt = number_format($customer->getOriginal('current_debt'), 2);
            $newDebt = number_format($changes['current_debt'], 2);
            return "{$user} updated debt for {$customer->name} from KES {$oldDebt} to KES {$newDebt}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} customer {$customer->name} ({$customer->customer_number})";
        }

        // Customer type change
        if (isset($changes['customer_type'])) {
            $oldType = $customer->getOriginal('customer_type');
            $newType = $changes['customer_type'];
            return "{$user} changed customer {$customer->name} type from {$oldType} to {$newType}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated customer {$customer->name} ({$customer->customer_number}) - {$changedFields}";
    }

    /**
     * Generate description for customer deletion
     */
    private function generateDeletionDescription(Customer $customer): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} deleted customer {$customer->name} ({$customer->customer_number})";
    }

    /**
     * Generate description for customer restoration
     */
    private function generateRestorationDescription(Customer $customer): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored customer {$customer->name} ({$customer->customer_number})";
    }
}
