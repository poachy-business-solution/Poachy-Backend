# Poachy Platform — Tenant Database ERD & Table Reference

> **76 tables · 20 domains** — One database per merchant/store (stancl/tenancy separate-database model).

---

## Cardinality Key

| Notation | Meaning |
|---|---|
| `||--||` | One-to-one (mandatory both sides) |
| `||--o{` | One-to-many (parent required, children optional) |
| `|o--o{` | One-to-many (nullable FK — parent optional) |
| `|o--o{` self | Self-referencing hierarchy or recurrence chain |

---

## Entity Relationship Diagram

```mermaid
erDiagram

    users { bigint id PK varchar name varchar email UK boolean is_active }
    password_reset_tokens { varchar email PK varchar token }
    personal_access_tokens { bigint id PK varchar token UK timestamp expires_at }
    tenant_otps { bigint id PK bigint user_id FK varchar type boolean is_used }
    permissions { bigint id PK varchar name varchar guard_name }
    roles { bigint id PK varchar name varchar guard_name }
    role_has_permissions { bigint permission_id FK bigint role_id FK }
    model_has_roles { bigint role_id FK varchar model_type bigint model_id }
    model_has_permissions { bigint permission_id FK varchar model_type bigint model_id }
    stores { bigint id PK varchar code UK boolean is_main_store bigint manager_id FK }
    tenant_delivery_zones { bigint id PK varchar zone_name varchar zone_type decimal standard_fee boolean is_active }
    product_categories { bigint id PK bigint parent_id FK varchar slug UK boolean is_active }
    product_brands { bigint id PK varchar slug UK boolean is_featured boolean is_active }
    products { bigint id PK varchar sku UK bigint category_id FK bigint brand_id FK bigint base_uom_id FK bigint tax_rate_id FK boolean is_active }
    product_variants { bigint id PK bigint product_id FK varchar sku UK bigint uom_id FK decimal variant_price }
    product_uoms { bigint id PK bigint product_id FK bigint uom_id FK boolean is_base_uom decimal conversion_to_base }
    product_bundles { bigint id PK varchar bundle_sku UK bigint base_uom_id FK bigint tax_rate_id FK boolean is_active }
    product_bundle_items { bigint id PK bigint bundle_id FK bigint product_id FK bigint product_variant_id FK bigint uom_id FK decimal quantity }
    product_barcodes { bigint id PK varchar barcodeable_type bigint barcodeable_id varchar barcode bigint supplier_id FK bigint store_id FK }
    product_price_history { bigint id PK bigint product_id FK bigint product_variant_id FK decimal new_selling_price bigint changed_by FK }
    suppliers { bigint id PK varchar name varchar supplier_type decimal credit_limit varchar payment_terms boolean is_active }
    units_of_measure { bigint id PK varchar code UK varchar type boolean is_base_unit boolean is_active }
    uom_conversions { bigint id PK bigint from_uom_id FK bigint to_uom_id FK decimal conversion_factor }
    tax_rates { bigint id PK varchar tax_name decimal rate boolean is_active boolean is_default }
    inventory { bigint id PK bigint store_id FK bigint product_id FK bigint product_variant_id FK decimal quantity_on_hand decimal quantity_available }
    inventory_movements { bigint id PK bigint store_id FK bigint product_id FK varchar movement_type decimal quantity decimal balance_after bigint created_by_user FK }
    inventory_reservations { bigint id PK bigint inventory_id FK decimal quantity_reserved varchar status }
    stock_alerts { bigint id PK bigint store_id FK bigint product_id FK varchar alert_type boolean is_resolved }
    stock_transfers { bigint id PK varchar transfer_number UK bigint from_store_id FK bigint to_store_id FK varchar status bigint requested_by FK }
    stock_transfer_items { bigint id PK bigint transfer_id FK bigint product_id FK bigint uom_id FK decimal quantity_requested decimal quantity_received }
    product_batches { bigint id PK bigint store_id FK bigint product_id FK bigint purchase_order_id FK varchar batch_number UK date expiry_date boolean is_expired }
    expiry_alerts { bigint id PK bigint batch_id FK varchar alert_level int days_until_expiry boolean is_resolved }
    inventory_waste { bigint id PK bigint store_id FK bigint product_id FK bigint batch_id FK varchar waste_type decimal total_loss }
    purchase_orders { bigint id PK varchar po_number UK bigint supplier_id FK bigint store_id FK varchar status decimal total_amount bigint created_by FK }
    purchase_order_items { bigint id PK bigint purchase_order_id FK bigint product_id FK bigint uom_id FK bigint tax_rate_id FK decimal unit_cost }
    supplier_payments { bigint id PK varchar payment_number UK bigint supplier_id FK bigint purchase_order_id FK decimal amount bigint created_by FK }
    store_products { bigint id PK bigint store_id FK bigint product_id FK bigint product_variant_id FK decimal store_selling_price boolean is_available }
    customers { bigint id PK varchar customer_number UK varchar phone UK varchar customer_type decimal loyalty_points bigint preferred_store_id FK }
    customer_groups { bigint id PK varchar name decimal discount_percentage boolean is_active }
    customer_group_members { bigint id PK bigint customer_id FK bigint group_id FK timestamp joined_at }
    loyalty_transactions { bigint id PK bigint customer_id FK varchar transaction_type decimal points decimal balance_after }
    customer_credit_transactions { bigint id PK bigint customer_id FK varchar transaction_type decimal amount decimal balance_after bigint created_by FK }
    coupons { bigint id PK varchar code UK varchar discount_type decimal discount_value date valid_until boolean is_active }
    coupon_products { bigint id PK bigint coupon_id FK bigint product_id FK bigint product_variant_id FK }
    coupon_categories { bigint id PK bigint coupon_id FK bigint category_id FK }
    coupon_brands { bigint id PK bigint coupon_id FK bigint brand_id FK }
    coupon_usage { bigint id PK bigint coupon_id FK bigint customer_id FK bigint sale_id FK decimal discount_applied }
    promotions { bigint id PK varchar code UK varchar promotion_type timestamp start_date timestamp end_date boolean is_active boolean auto_apply }
    promotion_products { bigint id PK bigint promotion_id FK bigint product_id FK bigint product_variant_id FK }
    promotion_categories { bigint id PK bigint promotion_id FK bigint category_id FK }
    promotion_brands { bigint id PK bigint promotion_id FK bigint brand_id FK }
    promotion_usage { bigint id PK bigint promotion_id FK bigint customer_id FK bigint sale_id FK decimal discount_applied }
    sales { bigint id PK varchar sale_number UK bigint store_id FK bigint customer_id FK bigint shift_assignment_id FK decimal total_amount varchar payment_method bigint served_by FK }
    sale_items { bigint id PK bigint sale_id FK bigint product_id FK bigint uom_id FK bigint tax_rate_id FK decimal quantity decimal unit_price }
    sale_payments { bigint id PK bigint sale_id FK decimal amount varchar payment_method bigint received_by_user_id FK }
    sale_refunds { bigint id PK varchar refund_number UK bigint original_sale_id FK bigint store_id FK varchar status bigint processed_by FK bigint exchange_sale_id FK }
    sale_refund_items { bigint id PK bigint refund_id FK bigint sale_item_id FK decimal quantity_refunded decimal refund_amount }
    marketplace_sales { bigint id PK bigint central_order_id varchar sale_number UK bigint store_id FK decimal total_amount varchar fulfillment_type }
    marketplace_sale_items { bigint id PK bigint marketplace_sale_id FK bigint product_id FK bigint uom_id FK decimal quantity decimal unit_price }
    product_reviews { bigint id PK bigint central_review_id UK bigint product_id decimal rating varchar status text merchant_response }
    expense_categories { bigint id PK bigint parent_id FK varchar code UK boolean requires_approval boolean is_active }
    expenses { bigint id PK varchar expense_number UK bigint store_id FK bigint category_id FK decimal amount bigint parent_expense_id FK bigint created_by FK }
    budgets { bigint id PK bigint store_id FK bigint category_id FK varchar period_type decimal budget_amount decimal spent_amount bigint created_by FK }
    shifts { bigint id PK varchar shift_name bigint store_id FK time scheduled_start_time boolean is_active }
    shift_assignments { bigint id PK bigint shift_id FK bigint store_id FK bigint user_id FK date shift_date varchar status }
    shift_sales_summary { bigint id PK bigint shift_assignment_id FK decimal total_sales_amount int total_transactions }
    shift_swap_requests { bigint id PK bigint requester_assignment_id FK bigint target_assignment_id FK bigint requester_id FK bigint target_user_id FK }
    sales_daily_aggregates { bigint id PK date aggregate_date bigint store_id FK varchar sellable_type bigint product_id FK decimal total_revenue decimal total_profit }
    audit_logs { bigint id PK bigint user_id FK varchar action varchar model_type bigint model_id json old_values }
    sync_queue_outbound { bigint id PK varchar syncable_type bigint syncable_id varchar status varchar idempotency_key UK bigint created_by FK }
    tenant_configurations { bigint id PK varchar config_key UK json config_value varchar config_type boolean is_active }
    cache { varchar key PK mediumtext value int expiration }
    cache_locks { varchar key PK varchar owner int expiration }
    jobs { bigint id PK varchar queue longtext payload tinyint attempts }
    job_batches { varchar id PK varchar name int total_jobs int failed_jobs }
    failed_jobs { bigint id PK varchar uuid UK longtext payload timestamp failed_at }

    users ||--o{ tenant_otps : "user_id"
    users |o--o{ stores : "manager_id"
    permissions ||--o{ role_has_permissions : "permission_id"
    roles ||--o{ role_has_permissions : "role_id"
    roles ||--o{ model_has_roles : "role_id"
    permissions ||--o{ model_has_permissions : "permission_id"
    product_categories |o--o{ product_categories : "parent_id"
    product_categories ||--o{ products : "category_id"
    product_brands |o--o{ products : "brand_id"
    suppliers |o--o{ products : "supplier_id"
    tax_rates |o--o{ products : "tax_rate_id"
    units_of_measure ||--o{ products : "base_uom_id"
    products ||--o{ product_variants : "product_id"
    units_of_measure ||--o{ product_variants : "uom_id"
    products ||--o{ product_uoms : "product_id"
    units_of_measure ||--o{ product_uoms : "uom_id"
    tax_rates ||--o{ product_bundles : "tax_rate_id"
    units_of_measure ||--o{ product_bundles : "base_uom_id"
    product_bundles ||--o{ product_bundle_items : "bundle_id"
    products ||--o{ product_bundle_items : "product_id"
    product_variants |o--o{ product_bundle_items : "product_variant_id"
    units_of_measure ||--o{ product_bundle_items : "uom_id"
    suppliers |o--o{ product_barcodes : "supplier_id"
    stores |o--o{ product_barcodes : "store_id"
    products ||--o{ product_price_history : "product_id"
    product_variants |o--o{ product_price_history : "product_variant_id"
    units_of_measure ||--o{ product_price_history : "base_uom_id"
    users ||--o{ product_price_history : "changed_by"
    units_of_measure ||--o{ uom_conversions : "from_uom_id"
    units_of_measure ||--o{ uom_conversions : "to_uom_id"
    stores ||--o{ inventory : "store_id"
    products ||--o{ inventory : "product_id"
    product_variants |o--o{ inventory : "product_variant_id"
    inventory ||--o{ inventory_reservations : "inventory_id"
    stores ||--o{ inventory_movements : "store_id"
    products ||--o{ inventory_movements : "product_id"
    product_variants |o--o{ inventory_movements : "product_variant_id"
    units_of_measure ||--o{ inventory_movements : "uom_id"
    users ||--o{ inventory_movements : "created_by_user"
    stores ||--o{ stock_alerts : "store_id"
    products ||--o{ stock_alerts : "product_id"
    product_variants |o--o{ stock_alerts : "product_variant_id"
    stores ||--o{ stock_transfers : "from_store_id"
    stores ||--o{ stock_transfers : "to_store_id"
    users ||--o{ stock_transfers : "requested_by"
    stock_transfers ||--o{ stock_transfer_items : "transfer_id"
    products ||--o{ stock_transfer_items : "product_id"
    product_variants |o--o{ stock_transfer_items : "product_variant_id"
    units_of_measure ||--o{ stock_transfer_items : "uom_id"
    stores ||--o{ product_batches : "store_id"
    products ||--o{ product_batches : "product_id"
    product_variants |o--o{ product_batches : "product_variant_id"
    purchase_orders ||--o{ product_batches : "purchase_order_id"
    suppliers |o--o{ product_batches : "supplier_id"
    units_of_measure ||--o{ product_batches : "purchase_uom_id"
    product_batches ||--o{ expiry_alerts : "batch_id"
    stores ||--o{ inventory_waste : "store_id"
    products ||--o{ inventory_waste : "product_id"
    product_batches |o--o{ inventory_waste : "batch_id"
    users ||--o{ inventory_waste : "reported_by"
    suppliers ||--o{ purchase_orders : "supplier_id"
    stores ||--o{ purchase_orders : "store_id"
    users ||--o{ purchase_orders : "created_by"
    purchase_orders ||--o{ purchase_order_items : "purchase_order_id"
    products ||--o{ purchase_order_items : "product_id"
    product_variants |o--o{ purchase_order_items : "product_variant_id"
    units_of_measure ||--o{ purchase_order_items : "uom_id"
    tax_rates ||--o{ purchase_order_items : "tax_rate_id"
    suppliers ||--o{ supplier_payments : "supplier_id"
    purchase_orders |o--o{ supplier_payments : "purchase_order_id"
    users ||--o{ supplier_payments : "created_by"
    stores ||--o{ store_products : "store_id"
    products ||--o{ store_products : "product_id"
    product_variants |o--o{ store_products : "product_variant_id"
    stores |o--o{ customers : "preferred_store_id"
    customers ||--o{ customer_group_members : "customer_id"
    customer_groups ||--o{ customer_group_members : "group_id"
    customers ||--o{ loyalty_transactions : "customer_id"
    customers ||--o{ customer_credit_transactions : "customer_id"
    users ||--o{ customer_credit_transactions : "created_by"
    coupons ||--o{ coupon_products : "coupon_id"
    products ||--o{ coupon_products : "product_id"
    product_variants |o--o{ coupon_products : "product_variant_id"
    coupons ||--o{ coupon_categories : "coupon_id"
    product_categories ||--o{ coupon_categories : "category_id"
    coupons ||--o{ coupon_brands : "coupon_id"
    product_brands ||--o{ coupon_brands : "brand_id"
    coupons ||--o{ coupon_usage : "coupon_id"
    customers |o--o{ coupon_usage : "customer_id"
    sales ||--o{ coupon_usage : "sale_id"
    promotions ||--o{ promotion_products : "promotion_id"
    products ||--o{ promotion_products : "product_id"
    product_variants |o--o{ promotion_products : "product_variant_id"
    promotions ||--o{ promotion_categories : "promotion_id"
    product_categories ||--o{ promotion_categories : "category_id"
    promotions ||--o{ promotion_brands : "promotion_id"
    product_brands ||--o{ promotion_brands : "brand_id"
    promotions ||--o{ promotion_usage : "promotion_id"
    customers |o--o{ promotion_usage : "customer_id"
    sales ||--o{ promotion_usage : "sale_id"
    stores ||--o{ sales : "store_id"
    shift_assignments |o--o{ sales : "shift_assignment_id"
    customers |o--o{ sales : "customer_id"
    coupons |o--o{ sales : "coupon_id"
    users ||--o{ sales : "served_by"
    sales ||--o{ sale_items : "sale_id"
    products ||--o{ sale_items : "product_id"
    product_variants |o--o{ sale_items : "product_variant_id"
    product_bundles |o--o{ sale_items : "bundle_id"
    units_of_measure ||--o{ sale_items : "uom_id"
    tax_rates ||--o{ sale_items : "tax_rate_id"
    sales ||--o{ sale_payments : "sale_id"
    users ||--o{ sale_payments : "received_by_user_id"
    sales ||--o{ sale_refunds : "original_sale_id"
    stores ||--o{ sale_refunds : "store_id"
    customers |o--o{ sale_refunds : "customer_id"
    users ||--o{ sale_refunds : "processed_by"
    sales |o--o{ sale_refunds : "exchange_sale_id"
    sale_refunds ||--o{ sale_refund_items : "refund_id"
    sale_items ||--o{ sale_refund_items : "sale_item_id"
    products ||--o{ sale_refund_items : "product_id"
    stores ||--o{ marketplace_sales : "store_id"
    marketplace_sales ||--o{ marketplace_sale_items : "marketplace_sale_id"
    products ||--o{ marketplace_sale_items : "product_id"
    product_variants |o--o{ marketplace_sale_items : "product_variant_id"
    product_bundles |o--o{ marketplace_sale_items : "bundle_id"
    units_of_measure ||--o{ marketplace_sale_items : "uom_id"
    expense_categories |o--o{ expense_categories : "parent_id"
    expense_categories ||--o{ expenses : "category_id"
    stores |o--o{ expenses : "store_id"
    suppliers |o--o{ expenses : "supplier_id"
    expenses |o--o{ expenses : "parent_expense_id"
    users ||--o{ expenses : "created_by"
    expense_categories ||--o{ budgets : "category_id"
    stores |o--o{ budgets : "store_id"
    users ||--o{ budgets : "created_by"
    stores |o--o{ shifts : "store_id"
    shifts ||--o{ shift_assignments : "shift_id"
    stores ||--o{ shift_assignments : "store_id"
    users ||--o{ shift_assignments : "user_id"
    shift_assignments ||--|| shift_sales_summary : "shift_assignment_id"
    shift_assignments ||--o{ shift_swap_requests : "requester_assignment_id"
    shift_assignments ||--o{ shift_swap_requests : "target_assignment_id"
    users ||--o{ shift_swap_requests : "requester_id"
    users ||--o{ shift_swap_requests : "target_user_id"
    stores ||--o{ sales_daily_aggregates : "store_id"
    products |o--o{ sales_daily_aggregates : "product_id"
    product_variants |o--o{ sales_daily_aggregates : "product_variant_id"
    product_bundles |o--o{ sales_daily_aggregates : "bundle_id"
    product_categories |o--o{ sales_daily_aggregates : "category_id"
    users |o--o{ audit_logs : "user_id"
    users |o--o{ sync_queue_outbound : "created_by"
```

---

## Table Reference

### Domain 1 — Identity & Auth

| # | Table | Purpose |
|---|-------|---------|
| 1 | `users` | Core authentication entity for all tenant staff; stores credentials, profile, and login metadata. |
| 2 | `password_reset_tokens` | Temporary email-keyed tokens for verifying staff password-reset requests. |
| 3 | `personal_access_tokens` | Sanctum API bearer tokens enabling scoped, expirable authentication for integrations and mobile clients. |
| 4 | `tenant_otps` | Time-limited one-time passcodes (login, password reset) issued to staff with attempt and expiry tracking. |

### Domain 2 — Permissions (Spatie Laravel-Permission)

| # | Table | Purpose |
|---|-------|---------|
| 5 | `permissions` | Named, guard-scoped permission atoms that represent individual access rights within the tenant system. |
| 6 | `roles` | Named, guard-scoped role definitions grouping permissions for staff assignment. |
| 7 | `role_has_permissions` | Pivot linking roles to their set of granted permissions. |
| 8 | `model_has_roles` | Polymorphic pivot assigning roles to any Eloquent model (typically staff users). |
| 9 | `model_has_permissions` | Polymorphic pivot granting direct permissions to any Eloquent model, bypassing role assignment. |

### Domain 3 — Core Store & Location

| # | Table | Purpose |
|---|-------|---------|
| 10 | `stores` | Physical store or branch locations; the primary multi-store organisational unit for the tenant. |
| 11 | `tenant_delivery_zones` | Merchant-defined delivery coverage areas with fee tiers per method and free-delivery thresholds, synced to the central marketplace. |

### Domain 4 — Product Catalog

| # | Table | Purpose |
|---|-------|---------|
| 12 | `product_categories` | Self-referencing hierarchy of product classifications for organising the catalog. |
| 13 | `product_brands` | Brand registry for products sold by the tenant, supporting featured and ordered display. |
| 14 | `products` | Master product catalog; one row per SKU capturing pricing, UoM, tax rate, category, brand, supplier, and online availability flags. |
| 15 | `product_variants` | Variant-level extensions of a parent product (e.g. size, colour) with individual SKU, pricing, and stock status. |
| 16 | `product_uoms` | Junction table linking products to all their supported units of measure, with base/purchase/sales role flags and conversion ratios. |
| 17 | `product_bundles` | Pre-packaged bundles sold as a single unit with a computed bundle price, tax, and optional online listing. |
| 18 | `product_bundle_items` | Line-level components comprising a bundle, specifying product or variant, UoM, and quantity. |
| 19 | `product_barcodes` | Polymorphic barcode registry for products, variants, and product-UoM entries, supporting multiple symbologies (EAN, UPC, CODE-128, QR, Scale). |
| 20 | `product_price_history` | Immutable log of every selling-price change for a product or variant, with reason code and effective timestamp. |

### Domain 5 — Suppliers, UOM & Tax

| # | Table | Purpose |
|---|-------|---------|
| 21 | `suppliers` | Supplier and vendor directory with contact details, credit terms, outstanding balance, and performance rating. |
| 22 | `units_of_measure` | Master list of all measurement units (weight, volume, count, length, area) available across the tenant system. |
| 23 | `uom_conversions` | Bidirectional conversion factors between any two units of measure. |
| 24 | `tax_rates` | Tax rate definitions with date-based validity windows, applied to products, bundles, and order lines. |

### Domain 6 — Inventory

| # | Table | Purpose |
|---|-------|---------|
| 25 | `inventory` | Current stock levels per product/variant per store: on-hand, reserved, available, and damaged quantities. |
| 26 | `inventory_movements` | Immutable ledger of every stock movement (purchase, sale, adjustment, transfer, waste, etc.) with a running balance. |
| 27 | `inventory_reservations` | Temporary stock holds against an inventory record for an in-progress order or workflow. |
| 28 | `stock_alerts` | System-generated low-stock, out-of-stock, and expiry-proximity alerts per product per store. |

### Domain 7 — Stock Transfers

| # | Table | Purpose |
|---|-------|---------|
| 29 | `stock_transfers` | Header record for inter-store stock movement requests, tracking the approval, dispatch, and receipt lifecycle. |
| 30 | `stock_transfer_items` | Line items for a stock transfer, recording requested, sent, and received quantities per product/UoM. |

### Domain 8 — Product Batches & Expiry

| # | Table | Purpose |
|---|-------|---------|
| 31 | `product_batches` | Batch-level stock tracking linking received quantity, unit cost, expiry date, and supplier to a specific purchase order. |
| 32 | `expiry_alerts` | Alerts raised when a batch is nearing or past its expiry date, with severity level and resolution action tracking. |
| 33 | `inventory_waste` | Records of stock written off due to expiry, damage, theft, or quality issues, with cost impact and approval workflow. |

### Domain 9 — Purchase Orders

| # | Table | Purpose |
|---|-------|---------|
| 34 | `purchase_orders` | Header for supplier purchase orders, tracking delivery schedule, totals, and payment status through its full lifecycle. |
| 35 | `purchase_order_items` | Line items for a purchase order, recording ordered vs. received quantities, unit costs, and applicable tax. |
| 36 | `supplier_payments` | Payments made to suppliers, with method, reference number, optional PO linkage, and approval metadata. |

### Domain 10 — Store Products

| # | Table | Purpose |
|---|-------|---------|
| 37 | `store_products` | Store-specific product overrides for availability flag, selling price, and minimum stock level, linking products to individual branches. |

### Domain 11 — Customers (In-Store)

| # | Table | Purpose |
|---|-------|---------|
| 38 | `customers` | In-store customer profiles with loyalty points, credit balance, outstanding debt, purchase history totals, and preferred store. |
| 39 | `customer_groups` | Named customer segments (e.g. VIP, Wholesale) with shared discount rules and optional approval requirements. |
| 40 | `customer_group_members` | Junction table enrolling customers into customer groups with an enrolment timestamp. |
| 41 | `loyalty_transactions` | Ledger of loyalty point credits, redemptions, expirations, and adjustments per customer with running balance. |
| 42 | `customer_credit_transactions` | Ledger of credit-based sales, repayments, adjustments, and write-offs per customer with running balance. |

### Domain 12 — Promotions & Coupons

| # | Table | Purpose |
|---|-------|---------|
| 43 | `coupons` | Discount coupon definitions with a unique code, discount type/value, validity window, and per-customer usage limits. |
| 44 | `coupon_products` | Links specific products or variants to which a coupon discount is applicable. |
| 45 | `coupon_categories` | Links specific product categories to which a coupon discount is applicable. |
| 46 | `coupon_brands` | Links specific brands to which a coupon discount is applicable. |
| 47 | `coupon_usage` | Records every coupon redemption against a specific sale and optional customer, for usage-count tracking. |
| 48 | `promotions` | Promotional campaign definitions supporting percentage, fixed, buy-X-get-Y, bundle, loyalty-bonus, and free-shipping types, with scheduling and store/group targeting. |
| 49 | `promotion_products` | Links specific products or variants to which a promotion applies. |
| 50 | `promotion_categories` | Links specific product categories to which a promotion applies. |
| 51 | `promotion_brands` | Links specific brands to which a promotion applies. |
| 52 | `promotion_usage` | Records every promotion application against a specific sale and optional customer, for usage-count and audit tracking. |

### Domain 13 — Sales & Transactions (POS)

| # | Table | Purpose |
|---|-------|---------|
| 53 | `sales` | POS sale header capturing store, cashier, customer, coupon, totals, payment method, shift assignment, and loyalty activity. |
| 54 | `sale_items` | Line items for a POS sale, preserving product/variant/bundle snapshots with quantity, price, cost, discount, and tax at time of sale. |
| 55 | `sale_payments` | Individual payment legs for a sale, enabling split and mixed payment methods per transaction. |
| 56 | `sale_refunds` | Refund request header against an original sale, tracking refund method, approval status, and optional exchange sale linkage. |
| 57 | `sale_refund_items` | Line items for a refund, recording the returned quantity and refund amount per original sale line. |

### Domain 14 — Marketplace (Online Sales)

| # | Table | Purpose |
|---|-------|---------|
| 58 | `marketplace_sales` | Tenant-side record of an online order received from the central marketplace platform, with fulfilment type and totals. |
| 59 | `marketplace_sale_items` | Line items for a marketplace sale, mirroring central order data with local product, UoM, and pricing detail. |
| 60 | `product_reviews` | Tenant-side copy of customer product reviews synced from the central marketplace, with merchant-response capability and sync status. |

### Domain 15 — Expenses & Budgets

| # | Table | Purpose |
|---|-------|---------|
| 61 | `expense_categories` | Self-referencing hierarchy of expense classifications with policy flags (receipt required, approval required, recurring eligible). |
| 62 | `expenses` | Individual expense records with amount, payment method, recurrence rules (self-referencing chain), supplier linkage, and approval workflow. |
| 63 | `budgets` | Expense budget allocations per store and category for a defined period, with real-time spend tracking and configurable alert threshold. |

### Domain 16 — Shifts & Staff Management

| # | Table | Purpose |
|---|-------|---------|
| 64 | `shifts` | Shift template definitions specifying scheduled hours, applicable days, and optional store assignment. |
| 65 | `shift_assignments` | Scheduled or active shift instances assigning a staff member to a shift on a specific date, with actual times and cash reconciliation. |
| 66 | `shift_sales_summary` | Aggregated sales totals for one completed shift assignment, broken down by payment method and including refund counts. |
| 67 | `shift_swap_requests` | Staff-initiated shift swap requests between two shift assignments, with manager approval workflow. |

### Domain 17 — Analytics & Reporting

| # | Table | Purpose |
|---|-------|---------|
| 68 | `sales_daily_aggregates` | Pre-computed daily sales metrics per store and sellable entity (product, variant, or bundle), enabling fast reporting queries. |
| 69 | `audit_logs` | Immutable change-log capturing every create, update, delete, and approval action across the tenant's business records, with old/new value snapshots. |

### Domain 18 — Tenant→Central Sync

| # | Table | Purpose |
|---|-------|---------|
| 70 | `sync_queue_outbound` | Durable outbound job queue for pushing tenant data changes (products, inventory, delivery zones, review responses) to the central marketplace platform, with retry, backoff, and idempotency support. |

### Domain 19 — Configuration

| # | Table | Purpose |
|---|-------|---------|
| 71 | `tenant_configurations` | Key-value store for all merchant-specific system settings: loyalty rules, receipt templates, credit policies, POS behaviour, and more. |

### Domain 20 — Infrastructure

| # | Table | Purpose |
|---|-------|---------|
| 72 | `cache` | Laravel database-backed cache store; key-value pairs with integer TTL expiration. |
| 73 | `cache_locks` | Atomic lock primitives for coordinating concurrent processes through the database cache driver. |
| 74 | `jobs` | Laravel queue job table; holds pending and reserved background job payloads routed across named queues. |
| 75 | `job_batches` | Tracks batched job group completion state (total, pending, failed counts) for bulk queue operations. |
| 76 | `failed_jobs` | Permanent log of all jobs that exhausted their retry budget, preserving payload and exception text for post-mortem debugging. |

---

_Generated: Poachy Tenant DB · 20 domains · 76 tables_
