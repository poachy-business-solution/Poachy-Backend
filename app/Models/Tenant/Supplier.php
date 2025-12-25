<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\PaymentTerms;
use App\Enums\Tenant\SupplierType;
use App\Observers\Tenant\SupplierObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(SupplierObserver::class)]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'supplier_type',
        'contact_person',
        'email',
        'phone',
        'address',
        'credit_limit',
        'outstanding_balance',
        'payment_terms',
        'registration_number',
        'bank_account_details',
        'rating',
        'total_orders',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'supplier_type' => SupplierType::class,
        'payment_terms' => PaymentTerms::class,
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'rating' => 'decimal:2',
        'total_orders' => 'integer',
        'is_active' => 'boolean',
        'bank_account_details' => 'array',
    ];

    protected $attributes = [
        'credit_limit' => 0,
        'outstanding_balance' => 0,
        'payment_terms' => 'cod',
        'rating' => 0,
        'total_orders' => 0,
        'is_active' => true,
    ];

    // Relationships

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }


    // Helper & Attribute Methods

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function hasFinancialDetails(): bool
    {
        return !empty($this->bank_account_details);
    }

    public function getProductCountAttribute(): int
    {
        return $this->products()->count();
    }

    public function getSupplierTypeDisplayAttribute(): string
    {
        return $this->supplier_type?->displayName() ?? '';
    }

    public function getPaymentTermsDisplayAttribute(): string
    {
        return $this->payment_terms?->displayName() ?? '';
    }

    public function getPaymentTermsDaysAttribute(): int
    {
        return $this->payment_terms?->days() ?? 0;
    }

    public function getTotalOutstandingAttribute(): float
    {
        return $this->purchaseOrders()
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->sum('amount_due');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function scopeBySupplierType($query, SupplierType|string $type)
    {
        $typeValue = $type instanceof SupplierType ? $type->value : $type;
        return $query->where('supplier_type', $typeValue);
    }

    public function scopeByPaymentTerms($query, PaymentTerms|string $terms)
    {
        $termsValue = $terms instanceof PaymentTerms ? $terms->value : $terms;
        return $query->where('payment_terms', $termsValue);
    }
}
