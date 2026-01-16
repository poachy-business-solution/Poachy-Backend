<?php

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
        \App\Models\Tenant\Sale::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['total_amount', 'payment_status', 'order_status'],
            'aggregate_children' => ['items', 'payments'], // Include in parent audit
            'default_tags' => ['sale', 'transaction', 'financial'],
        ],

        \App\Models\Tenant\SaleItem::class => [
            'audit_mode' => 'none', // Included in Sale audit
        ],

        \App\Models\Tenant\SalePayment::class => [
            'audit_mode' => 'none', // Included in Sale audit
        ],

        \App\Models\Tenant\SaleRefund::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['refund_amount', 'refund_method'],
            'aggregate_children' => ['items'],
            'default_tags' => ['refund', 'transaction', 'financial'],
        ],

        \App\Models\Tenant\SaleRefundItem::class => [
            'audit_mode' => 'none', // Included in SaleRefund audit
        ],

        \App\Models\Tenant\PurchaseOrder::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['status', 'total_amount', 'payment_status'],
            'aggregate_children' => ['items'],
            'default_tags' => ['purchase_order', 'procurement', 'financial'],
        ],

        \App\Models\Tenant\PurchaseOrderItem::class => [
            'audit_mode' => 'none', // Included in PurchaseOrder audit
        ],

        \App\Models\Tenant\Expense::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'approval_status', 'payment_status'],
            'default_tags' => ['expense', 'financial'],
        ],

        \App\Models\Tenant\SupplierPayment::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'payment_method'],
            'default_tags' => ['supplier_payment', 'financial'],
        ],

        \App\Models\Tenant\CustomerCreditTransaction::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['amount', 'transaction_type', 'balance_after'],
            'default_tags' => ['credit', 'customer', 'financial'],
        ],

        \App\Models\Tenant\LoyaltyTransaction::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['points', 'transaction_type', 'balance_after'],
            'default_tags' => ['loyalty', 'customer'],
        ],

        // ========================================
        // INVENTORY & PRODUCT MODELS (SELECTIVE)
        // ========================================
        \App\Models\Tenant\Product::class => [
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

        \App\Models\Tenant\ProductVariant::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => [
                'variant_price',
                'base_selling_price_adjustment',
                'stock_status',
                'is_active',
            ],
            'default_tags' => ['product_variant', 'inventory'],
        ],

        \App\Models\Tenant\Inventory::class => [
            'audit_mode' => 'none', // Use InventoryMovement instead
        ],

        \App\Models\Tenant\InventoryMovement::class => [
            'audit_mode' => 'none', // Self-auditing table
        ],

        \App\Models\Tenant\ProductBatch::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['is_expired', 'quantity_remaining_in_base_uom'],
            'default_tags' => ['batch', 'inventory'],
        ],

        \App\Models\Tenant\InventoryWaste::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['quantity_wasted', 'total_loss', 'approval_status'],
            'default_tags' => ['waste', 'inventory', 'loss'],
        ],

        \App\Models\Tenant\StockTransfer::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['status'],
            'aggregate_children' => ['items'],
            'default_tags' => ['stock_transfer', 'inventory'],
        ],

        \App\Models\Tenant\StockTransferItem::class => [
            'audit_mode' => 'none', // Included in StockTransfer audit
        ],

        // ========================================
        // CUSTOMER & SUPPLIER MODELS (MODERATE)
        // ========================================
        \App\Models\Tenant\Customer::class => [
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

        \App\Models\Tenant\Supplier::class => [
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

        \App\Models\Tenant\CustomerGroup::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'discount_percentage', 'is_active'],
            'default_tags' => ['customer_group', 'configuration'],
        ],

        // ========================================
        // CONFIGURATION MODELS (LIGHT)
        // ========================================
        \App\Models\Tenant\Store::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active', 'is_main_store'],
            'default_tags' => ['store', 'configuration'],
        ],

        \App\Models\Tenant\TaxRate::class => [
            'audit_mode' => 'full',
            'critical_fields' => ['rate', 'effective_from', 'is_active'],
            'default_tags' => ['tax', 'configuration', 'critical'],
        ],

        \App\Models\Tenant\ExpenseCategory::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active'],
            'default_tags' => ['expense_category', 'configuration'],
        ],

        \App\Models\Tenant\ProductCategory::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'parent_id', 'is_active'],
            'default_tags' => ['product_category', 'configuration'],
        ],

        \App\Models\Tenant\ProductBrand::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'is_active'],
            'default_tags' => ['product_brand', 'configuration'],
        ],

        \App\Models\Tenant\UnitOfMeasure::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['name', 'code', 'is_active'],
            'default_tags' => ['uom', 'configuration'],
        ],

        \App\Models\Tenant\TenantConfiguration::class => [
            'audit_mode' => 'full',
            'default_tags' => ['tenant_config', 'configuration', 'critical'],
        ],

        // ========================================
        // PROMOTIONAL MODELS (CONDITIONAL)
        // ========================================
        \App\Models\Tenant\Coupon::class => [
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

        \App\Models\Tenant\CouponUsage::class => [
            'audit_mode' => 'none', // Self-auditing table (usage tracking)
        ],

        \App\Models\Tenant\Promotion::class => [
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

        \App\Models\Tenant\PromotionUsage::class => [
            'audit_mode' => 'none', // Self-auditing table (usage tracking)
        ],

        // ========================================
        // OPERATIONAL MODELS (MINIMAL/NO AUDIT)
        // ========================================
        \App\Models\Tenant\Budget::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['status'], // Only audit approval/rejection
            'default_tags' => ['budget', 'financial'],
        ],

        \App\Models\Tenant\Shift::class => [
            'audit_mode' => 'none',
        ],

        \App\Models\Tenant\ShiftAssignment::class => [
            'audit_mode' => 'none',
        ],

        \App\Models\Tenant\ShiftSalesSummary::class => [
            'audit_mode' => 'none',
        ],

        \App\Models\Tenant\ShiftSwapRequest::class => [
            'audit_mode' => 'critical_only',
            'critical_fields' => ['status'], // Only audit approval
            'default_tags' => ['shift', 'hr'],
        ],

        \App\Models\Tenant\StockAlert::class => [
            'audit_mode' => 'none', // System-generated, ephemeral
        ],

        \App\Models\Tenant\ExpiryAlert::class => [
            'audit_mode' => 'none', // System-generated, ephemeral
        ],

        \App\Models\Tenant\SalesDailyAggregate::class => [
            'audit_mode' => 'none', // Derived data
        ],

        \App\Models\Tenant\ProductPriceHistory::class => [
            'audit_mode' => 'none', // Self-auditing table
        ],

        \App\Models\Tenant\InventoryReservation::class => [
            'audit_mode' => 'none', // Temporary, auto-expire
        ],

        // ========================================
        // SYSTEM/JUNCTION MODELS (NO AUDIT)
        // ========================================
        \App\Models\Tenant\User::class => [
            'audit_mode' => 'none', // Use separate authentication audit
        ],

        \App\Models\Tenant\TenantOtp::class => [
            'audit_mode' => 'none', // Security-sensitive, short-lived
        ],

        \App\Models\Tenant\ProductUom::class => [
            'audit_mode' => 'none', // Configuration data
        ],

        \App\Models\Tenant\UomConversion::class => [
            'audit_mode' => 'none', // Configuration data
        ],

        \App\Models\Tenant\ProductBundleItem::class => [
            'audit_mode' => 'none', // Junction table
        ],

        \App\Models\Tenant\StoreProduct::class => [
            'audit_mode' => 'none', // Junction table
        ],

        \App\Models\Tenant\CustomerGroupMember::class => [
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
