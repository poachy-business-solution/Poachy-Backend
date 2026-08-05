<?php

use App\Models\Tenant\Budget;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\CustomerGroupMember;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\ExpiryAlert;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\InventoryWaste;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductBundleItem;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ProductPriceHistory;
use App\Models\Tenant\ProductSerial;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\PromotionUsage;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Models\Tenant\SalesDailyAggregate;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftSalesSummary;
use App\Models\Tenant\ShiftSwapRequest;
use App\Models\Tenant\StockAlert;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\StockTransferItem;
use App\Models\Tenant\Store;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\TenantConfiguration;
use App\Models\Tenant\TenantOtp;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\UomConversion;
use App\Models\Tenant\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Logging Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether audit logging is enabled globally.
    | You can disable it in specific environments or for testing.
    |
    */
    'enabled' => env('AUDIT_LOGGING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | These fields will be automatically removed from audit values.
    | Useful for sensitive data like passwords, tokens, etc.
    |
    */
    'excluded_fields' => [
        'password',
        'remember_token',
        'updated_at',
        'last_login_at',
        'view_count',
        'total_visits',
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Auditing
    |--------------------------------------------------------------------------
    |
    | Enable async auditing to queue audit log creation.
    | Useful for high-volume operations to reduce request time.
    |
    */
    'async_enabled' => env('AUDIT_ASYNC_ENABLED', false),
    'async_queue' => env('AUDIT_QUEUE', 'sync-low'),

    /*
    |--------------------------------------------------------------------------
    | Model-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audit behavior per model:
    | - audit_mode: 'full', 'critical_only', 'none'
    | - critical_fields: Array of fields that trigger audit when changed
    | - aggregate_children: Relations to include in parent audit
    | - default_tags: Tags automatically added to audits
    |
    */
    'models' => [
        // ========================================
        // CRITICAL FINANCIAL MODELS (ALWAYS AUDIT)
        // ========================================
        Sale::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['total_amount', 'payment_status', 'order_status'],
            'aggregate_children' => ['items', 'payments'], // Include in parent audit
            'default_tags' => ['sale', 'transaction', 'financial'],
        ],

        SaleItem::class => [
            'audit_mode' => 'none', // Included in Sale audit
        ],

        SalePayment::class => [
            'audit_mode' => 'none', // Included in Sale audit
        ],

        SaleRefund::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['refund_amount', 'refund_method'],
            'aggregate_children' => ['items'],
            'default_tags' => ['refund', 'transaction', 'financial'],
        ],

        SaleRefundItem::class => [
            'audit_mode' => 'none', // Included in SaleRefund audit
        ],

        PurchaseOrder::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['status', 'total_amount', 'payment_status'],
            'aggregate_children' => ['items'],
            'default_tags' => ['purchase_order', 'procurement', 'financial'],
        ],

        PurchaseOrderItem::class => [
            'audit_mode' => 'none', // Included in PurchaseOrder audit
        ],

        Expense::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'approval_status', 'payment_status'],
            'default_tags' => ['expense', 'financial'],
        ],

        SupplierPayment::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'payment_method'],
            'default_tags' => ['supplier_payment', 'financial'],
        ],

        CustomerCreditTransaction::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'transaction_type', 'balance_after'],
            'default_tags' => ['credit', 'customer', 'financial'],
        ],

        LoyaltyTransaction::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['points', 'transaction_type', 'balance_after'],
            'default_tags' => ['loyalty', 'customer'],
        ],

        // ========================================
        // INVENTORY & PRODUCT MODELS (SELECTIVE)
        // ========================================
        Product::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'base_selling_price',
                'stock_status',
                'is_active',
                'is_available_online',
                'online_price',
                'product_type', // simple vs variable
            ],
            'default_tags' => ['product', 'inventory'],
        ],

        ProductVariant::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'variant_price',
                'base_selling_price_adjustment',
                'stock_status',
                'is_active',
            ],
            'default_tags' => ['product_variant', 'inventory'],
        ],

        Inventory::class => [
            'audit_mode' => 'none', // Use InventoryMovement instead
        ],

        InventoryMovement::class => [
            'audit_mode' => 'none', // Self-auditing table
        ],

        ProductBatch::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['is_expired', 'quantity_remaining_in_base_uom'],
            'default_tags' => ['batch', 'inventory'],
        ],

        ProductSerial::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['status'],
            'default_tags' => ['serial', 'inventory'],
        ],

        InventoryWaste::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['quantity_wasted', 'total_loss', 'approval_status'],
            'default_tags' => ['waste', 'inventory', 'loss'],
        ],

        StockTransfer::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['status'],
            'aggregate_children' => ['items'],
            'default_tags' => ['stock_transfer', 'inventory'],
        ],

        StockTransferItem::class => [
            'audit_mode' => 'none', // Included in StockTransfer audit
        ],

        // ========================================
        // CUSTOMER & SUPPLIER MODELS (MODERATE)
        // ========================================
        Customer::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'name',
                'email',
                'phone',
                'credit_limit',
                'current_debt',
                'is_active',
                'customer_type',
            ],
            'default_tags' => ['customer', 'profile'],
            'pii_fields' => ['email', 'phone', 'address'], // For GDPR compliance
        ],

        Supplier::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'name',
                'email',
                'phone',
                'credit_limit',
                'outstanding_balance',
                'payment_terms',
                'is_active',
            ],
            'default_tags' => ['supplier', 'profile'],
        ],

        CustomerGroup::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'discount_percentage', 'is_active'],
            'default_tags' => ['customer_group', 'configuration'],
        ],

        // ========================================
        // CONFIGURATION MODELS (LIGHT)
        // ========================================
        Store::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active', 'is_main_store'],
            'default_tags' => ['store', 'configuration'],
        ],

        TaxRate::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['rate', 'effective_from', 'is_active'],
            'default_tags' => ['tax', 'configuration', 'critical'],
        ],

        ExpenseCategory::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active'],
            'default_tags' => ['expense_category', 'configuration'],
        ],

        ProductCategory::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'parent_id', 'is_active'],
            'default_tags' => ['product_category', 'configuration'],
        ],

        ProductBrand::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active'],
            'default_tags' => ['product_brand', 'configuration'],
        ],

        UnitOfMeasure::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'code', 'is_active'],
            'default_tags' => ['uom', 'configuration'],
        ],

        TenantConfiguration::class => [
            'audit_mode' => 'full',
            'default_tags' => ['tenant_config', 'configuration', 'critical'],
        ],

        // ========================================
        // PROMOTIONAL MODELS (CONDITIONAL)
        // ========================================
        Coupon::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'code',
                'discount_value',
                'valid_from',
                'valid_until',
                'is_active',
            ],
            'default_tags' => ['coupon', 'promotion'],
        ],

        CouponUsage::class => [
            'audit_mode' => 'none', // Self-auditing table (usage tracking)
        ],

        Promotion::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'name',
                'discount_value',
                'start_date',
                'end_date',
                'is_active',
            ],
            'default_tags' => ['promotion', 'marketing'],
        ],

        PromotionUsage::class => [
            'audit_mode' => 'none', // Self-auditing table (usage tracking)
        ],

        // ========================================
        // OPERATIONAL MODELS (MINIMAL/NO AUDIT)
        // ========================================
        Budget::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['status'], // Only audit approval/rejection
            'default_tags' => ['budget', 'financial'],
        ],

        Shift::class => [
            'audit_mode' => 'none',
        ],

        ShiftAssignment::class => [
            'audit_mode' => 'none',
        ],

        ShiftSalesSummary::class => [
            'audit_mode' => 'none',
        ],

        ShiftSwapRequest::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['status'], // Only audit approval
            'default_tags' => ['shift', 'hr'],
        ],

        StockAlert::class => [
            'audit_mode' => 'none', // System-generated, ephemeral
        ],

        ExpiryAlert::class => [
            'audit_mode' => 'none', // System-generated, ephemeral
        ],

        SalesDailyAggregate::class => [
            'audit_mode' => 'none', // Derived data
        ],

        ProductPriceHistory::class => [
            'audit_mode' => 'none', // Self-auditing table
        ],

        InventoryReservation::class => [
            'audit_mode' => 'none', // Temporary, auto-expire
        ],

        // ========================================
        // SYSTEM/JUNCTION MODELS (NO AUDIT)
        // ========================================
        User::class => [
            'audit_mode' => 'none', // Use separate authentication audit
        ],

        TenantOtp::class => [
            'audit_mode' => 'none', // Security-sensitive, short-lived
        ],

        ProductUom::class => [
            'audit_mode' => 'none', // Configuration data
        ],

        UomConversion::class => [
            'audit_mode' => 'none', // Configuration data
        ],

        ProductBundleItem::class => [
            'audit_mode' => 'none', // Junction table
        ],

        StoreProduct::class => [
            'audit_mode' => 'none', // Junction table
        ],

        CustomerGroupMember::class => [
            'audit_mode' => 'none', // Junction table
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk Operation Settings
    |--------------------------------------------------------------------------
    |
    | When bulk operations affect multiple records, create a summary audit
    | instead of individual audits for each record.
    |
    */
    'bulk_operations' => [
        'summary_threshold' => 10, // Create summary if bulk affects 10+ records
        'max_individual_logs' => 5, // Max individual logs before switching to summary
    ],

    /*
    |--------------------------------------------------------------------------
    | Description Templates
    |--------------------------------------------------------------------------
    |
    | Templates for auto-generated audit descriptions.
    | Use {user}, {model}, {identifier}, {action} placeholders.
    |
    */
    'description_templates' => [
        'created' => '{user} created {model} {identifier}',
        'updated' => '{user} updated {model} {identifier}',
        'deleted' => '{user} deleted {model} {identifier}',
        'restored' => '{user} restored {model} {identifier}',
        'approved' => '{user} approved {model} {identifier}',
        'rejected' => '{user} rejected {model} {identifier}',
        'cancelled' => '{user} cancelled {model} {identifier}',
        'completed' => '{user} completed {model} {identifier}',
    ],
];
