<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\TaxRate;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaxRateObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function created(TaxRate $taxRate): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $taxRate,
                action: 'created',
                oldValues: null,
                newValues: $taxRate->toArray(),
                description: $this->generateCreationDescription($taxRate),
                tags: ['tax', 'configuration', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create tax rate audit log', [
                'tenant_id' => tenant()?->id,
                'tax_rate_id' => $taxRate->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Log warning if multiple active rates for same tax name
        $this->checkForDuplicateActiveTaxRates($taxRate);
    }

    public function updated(TaxRate $taxRate): void
    {
        $this->clearCache();

        try {
            // Full audit mode - always log updates
            if ($taxRate->wasChanged()) {
                $oldValues = $taxRate->getOriginal();
                $changes = $taxRate->getChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($taxRate, $changes);

                // Add specific tags based on changes
                $tags = ['tax', 'configuration', 'critical'];
                if (isset($changes['rate'])) {
                    $tags[] = 'rate_change';
                    $tags[] = 'financial';
                }
                if (isset($changes['is_active'])) {
                    $tags[] = 'status_change';
                }
                if (isset($changes['effective_from']) || isset($changes['effective_until'])) {
                    $tags[] = 'date_change';
                }
                if (isset($changes['is_default'])) {
                    $tags[] = 'default_change';
                }

                $this->auditService->createAudit(
                    model: $taxRate,
                    action: 'updated',
                    oldValues: $oldValues,
                    newValues: $changes,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create tax rate update audit log', [
                'tenant_id' => tenant()?->id,
                'tax_rate_id' => $taxRate->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Check for overlapping rates
        if ($taxRate->wasChanged(['effective_from', 'effective_until', 'is_active'])) {
            $this->checkForDuplicateActiveTaxRates($taxRate);
        }
    }

    public function deleted(TaxRate $taxRate): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $taxRate,
                action: 'deleted',
                oldValues: $taxRate->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($taxRate),
                tags: ['tax', 'configuration', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create tax rate deletion audit log', [
                'tenant_id' => tenant()?->id,
                'tax_rate_id' => $taxRate->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'tax_rates'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear tax rate cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function checkForDuplicateActiveTaxRates(TaxRate $taxRate): void
    {
        if (!$taxRate->is_active) {
            return;
        }

        $overlappingRates = TaxRate::where('tax_name', $taxRate->tax_name)
            ->where('id', '!=', $taxRate->id)
            ->where('is_active', true)
            ->where(function ($query) use ($taxRate) {
                $query->where(function ($q) use ($taxRate) {
                    // effective_from falls within range
                    $q->where('effective_from', '<=', $taxRate->effective_from)
                        ->where(function ($q2) use ($taxRate) {
                            $q2->whereNull('effective_until')
                                ->orWhere('effective_until', '>=', $taxRate->effective_from);
                        });
                })->orWhere(function ($q) use ($taxRate) {
                    // effective_until falls within range
                    if ($taxRate->effective_until) {
                        $q->where('effective_from', '<=', $taxRate->effective_until)
                            ->where(function ($q2) use ($taxRate) {
                                $q2->whereNull('effective_until')
                                    ->orWhere('effective_until', '>=', $taxRate->effective_until);
                            });
                    }
                });
            })
            ->count();

        if ($overlappingRates > 0) {
            Log::warning('Multiple active tax rates detected for same period', [
                'tenant_id' => tenant()->id,
                'tax_name' => $taxRate->tax_name,
                'tax_rate_id' => $taxRate->id,
                'overlapping_count' => $overlappingRates,
                'effective_from' => $taxRate->effective_from->toDateString(),
                'effective_until' => $taxRate->effective_until?->toDateString(),
            ]);
        }
    }

    private function generateCreationDescription(TaxRate $taxRate): string
    {
        $user = Auth::user()?->name ?? 'System';
        $rate = number_format($taxRate->rate, 2);
        $effectiveFrom = $taxRate->effective_from->format('M d, Y');
        $effectiveUntil = $taxRate->effective_until ? ' until ' . $taxRate->effective_until->format('M d, Y') : '';
        $default = $taxRate->is_default ? ' (set as default)' : '';

        return "{$user} created tax rate {$taxRate->tax_name} at {$rate}% effective from {$effectiveFrom}{$effectiveUntil}{$default}";
    }

    /**
     * Generate description for tax rate update
     */
    private function generateUpdateDescription(TaxRate $taxRate, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Rate change (most important)
        if (isset($changes['rate'])) {
            $oldRate = number_format($taxRate->getOriginal('rate'), 2);
            $newRate = number_format($changes['rate'], 2);
            return "{$user} changed {$taxRate->tax_name} tax rate from {$oldRate}% to {$newRate}%";
        }

        // Effective date changes
        if (isset($changes['effective_from'])) {
            $oldDate = \Carbon\Carbon::parse($taxRate->getOriginal('effective_from'))->format('M d, Y');
            $newDate = \Carbon\Carbon::parse($changes['effective_from'])->format('M d, Y');
            return "{$user} changed {$taxRate->tax_name} effective from date from {$oldDate} to {$newDate}";
        }

        if (isset($changes['effective_until'])) {
            $oldDate = $taxRate->getOriginal('effective_until')
                ? \Carbon\Carbon::parse($taxRate->getOriginal('effective_until'))->format('M d, Y')
                : 'ongoing';
            $newDate = $changes['effective_until']
                ? \Carbon\Carbon::parse($changes['effective_until'])->format('M d, Y')
                : 'ongoing';
            return "{$user} changed {$taxRate->tax_name} effective until date from {$oldDate} to {$newDate}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} tax rate {$taxRate->tax_name} ({$taxRate->rate}%)";
        }

        // Default status change
        if (isset($changes['is_default'])) {
            if ($changes['is_default']) {
                return "{$user} set {$taxRate->tax_name} as default tax rate";
            } else {
                return "{$user} removed default flag from {$taxRate->tax_name}";
            }
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated tax rate {$taxRate->tax_name} - {$changedFields}";
    }

    /**
     * Generate description for tax rate deletion
     */
    private function generateDeletionDescription(TaxRate $taxRate): string
    {
        $user = Auth::user()?->name ?? 'System';
        $rate = number_format($taxRate->rate, 2);

        return "{$user} deleted tax rate {$taxRate->tax_name} ({$rate}%)";
    }
}
