# Poachy Platform — Central Database ERD & Table Reference

> **50 tables · 10 domains** — Central multi-tenant marketplace schema.
> _Note: the prompt cited 44 tables; a full count of the documented schema yields 50._

---

## Cardinality Key

| Notation | Meaning |
|---|---|
| `\|\|--\|\|` | One-to-one (mandatory both sides) |
| `\|\|--o{` | One-to-many (parent required, children optional) |
| `\|o--o{` | One-to-many (nullable FK — parent optional) |
| `\|o--o{` self | Self-referencing hierarchy |

---

## Entity Relationship Diagram

```mermaid
erDiagram

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 1 — IDENTITY & AUTH
    %% ════════════════════════════════════════════════════════════════

    users {
        bigint id PK
        varchar user_type
        varchar name
        varchar email UK
        varchar password
        timestamp email_verified_at
    }

    password_reset_tokens {
        varchar email PK
        varchar token
        timestamp created_at
    }

    sessions {
        varchar id PK
        bigint user_id FK
        varchar ip_address
        int last_activity
        longtext payload
    }

    personal_access_tokens {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        varchar token UK
        text abilities
        timestamp expires_at
    }

    otps {
        bigint id PK
        bigint user_id FK
        varchar otp_code
        varchar type
        boolean is_used
        tinyint attempts
        timestamp expires_at
    }

    notifications {
        uuid id PK
        varchar type
        varchar notifiable_type
        bigint notifiable_id
        text data
        timestamp read_at
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 2 — PERMISSIONS (Spatie Laravel-Permission)
    %% ════════════════════════════════════════════════════════════════

    permissions {
        bigint id PK
        varchar name
        varchar guard_name
    }

    roles {
        bigint id PK
        varchar name
        varchar guard_name
    }

    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }

    model_has_roles {
        bigint role_id FK
        varchar model_type
        bigint model_morph_key
    }

    model_has_permissions {
        bigint permission_id FK
        varchar model_type
        bigint model_morph_key
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 3 — TENANCY & SUBSCRIPTIONS
    %% ════════════════════════════════════════════════════════════════

    tenants {
        varchar id PK
        json data
        timestamp created_at
    }

    domains {
        int id PK
        varchar domain UK
        boolean is_primary
        varchar tenant_id FK
    }

    business_types {
        bigint id PK
        varchar name
        varchar slug UK
        boolean is_active
    }

    business_categories {
        bigint id PK
        bigint business_type_id FK
        varchar name
        varchar slug UK
        boolean is_active
    }

    business_details {
        bigint id PK
        varchar tenant_id FK
        varchar business_name
        bigint business_type_id FK
        bigint business_category_id FK
        varchar business_phone
        varchar status
        boolean is_verified
        timestamp deleted_at
    }

    subscription_plans {
        bigint id PK
        varchar name
        varchar slug UK
        decimal price
        varchar currency
        int billing_cycle_days
        json features
        boolean is_active
    }

    business_subscriptions {
        bigint id PK
        varchar tenant_id FK
        bigint subscription_plan_id FK
        date start_date
        date end_date
        decimal amount_paid
        varchar status
        boolean is_trial
    }

    tenant_profiles {
        bigint id PK
        varchar tenant_id FK
        decimal average_overall_rating
        int total_reviews
        int total_orders
        decimal total_revenue
        int total_marketplace_products
    }

    tenant_delivery_zones {
        bigint id PK
        varchar tenant_id FK
        bigint tenant_zone_id
        varchar zone_name
        varchar zone_type
        decimal standard_fee
        decimal free_delivery_threshold
        boolean is_active
        timestamp last_synced_at
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 4 — MARKETPLACE CUSTOMERS
    %% ════════════════════════════════════════════════════════════════

    marketplace_customers {
        bigint id PK
        bigint user_id FK
        varchar customer_number UK
        varchar phone UK
        boolean is_active
        boolean phone_verified
        timestamp last_login_at
        timestamp deleted_at
    }

    customer_addresses {
        bigint id PK
        bigint customer_id FK
        varchar address_type
        varchar recipient_name
        varchar recipient_phone
        varchar city
        varchar county
        boolean is_default
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 5 — MARKETPLACE CATALOG
    %% ════════════════════════════════════════════════════════════════

    marketplace_categories {
        bigint id PK
        bigint parent_id FK
        varchar name
        varchar slug UK
        boolean is_featured
        boolean is_active
        int display_order
    }

    marketplace_brands {
        bigint id PK
        varchar name
        varchar slug UK
        boolean is_featured
        boolean is_active
        timestamp deleted_at
    }

    tenant_category_mappings {
        bigint id PK
        varchar tenant_id FK
        bigint tenant_category_id
        varchar tenant_category_name
        bigint marketplace_category_id FK
        decimal confidence_score
        boolean is_auto_mapped
        boolean is_verified
    }

    tenant_brand_mappings {
        bigint id PK
        varchar tenant_id FK
        bigint tenant_brand_id
        varchar tenant_brand_name
        bigint marketplace_brand_id FK
        decimal confidence_score
        boolean is_auto_mapped
        boolean is_verified
    }

    marketplace_products {
        bigint id PK
        varchar tenant_id FK
        bigint tenant_product_id
        varchar tenant_product_type
        varchar name
        varchar sku
        bigint marketplace_category_id FK
        bigint marketplace_brand_id FK
        decimal online_price
        decimal available_quantity
        varchar stock_status
        boolean is_active
        timestamp deleted_at
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 6 — MARKETPLACE ORDERS & PAYMENTS
    %% ════════════════════════════════════════════════════════════════

    marketplace_orders {
        bigint id PK
        varchar order_number UK
        bigint customer_id FK
        bigint delivery_address_id FK
        varchar tenant_id FK
        decimal total_amount
        varchar order_status
        varchar reservation_status
        varchar fulfillment_type
        timestamp deleted_at
    }

    marketplace_order_items {
        bigint id PK
        bigint order_id FK
        bigint marketplace_product_id FK
        varchar product_name
        varchar product_sku
        decimal quantity
        decimal unit_price
        decimal tax_amount
        decimal subtotal
        varchar fulfillment_status
    }

    marketplace_order_payments {
        bigint id PK
        bigint order_id FK
        varchar payment_method
        decimal amount
        varchar payment_status
        varchar transaction_reference UK
        boolean is_refunded
        decimal refunded_amount
        timestamp completed_at
    }

    marketplace_order_deliveries {
        bigint id PK
        bigint order_id FK
        varchar delivery_method
        varchar delivery_status
        varchar courier_name
        varchar tracking_number
        timestamp estimated_delivery_time
        timestamp actual_delivery_time
        int delivery_attempts
    }

    shopping_carts {
        bigint id PK
        bigint customer_id FK
        varchar session_id UK
        varchar status
        bigint converted_order_id FK
        timestamp abandoned_at
        timestamp converted_at
    }

    shopping_cart_items {
        bigint id PK
        bigint cart_id FK
        bigint marketplace_product_id FK
        varchar product_name
        varchar product_sku
        decimal quantity
        decimal unit_price
        timestamp added_at
    }

    checkout_sessions {
        bigint id PK
        bigint cart_id FK
        bigint customer_id FK
        varchar current_step
        boolean is_completed
        bigint completed_order_id FK
        boolean is_abandoned
        varchar abandoned_at_step
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 7 — REVIEWS & MODERATION
    %% ════════════════════════════════════════════════════════════════

    product_reviews {
        bigint id PK
        bigint marketplace_product_id FK
        bigint customer_id FK
        bigint order_id FK
        decimal rating
        text review_text
        boolean is_verified_purchase
        varchar status
        int helpful_count
        timestamp deleted_at
    }

    merchant_reviews {
        bigint id PK
        varchar tenant_id FK
        bigint customer_id FK
        bigint order_id FK
        decimal overall_rating
        decimal delivery_rating
        decimal service_rating
        varchar status
        int helpful_count
        timestamp deleted_at
    }

    review_votes {
        bigint id PK
        bigint customer_id FK
        varchar voteable_type
        bigint voteable_id
        varchar vote_type
    }

    review_flags {
        bigint id PK
        bigint customer_id FK
        varchar flaggable_type
        bigint flaggable_id
        text reason
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 8 — DISCOVERY & ANALYTICS
    %% ════════════════════════════════════════════════════════════════

    wishlists {
        bigint id PK
        bigint customer_id FK
        bigint marketplace_product_id FK
        decimal price_at_addition
        int desired_quantity
    }

    customer_journey_events {
        bigint id PK
        bigint customer_id FK
        varchar session_id
        varchar event_type
        bigint marketplace_product_id FK
        bigint marketplace_category_id FK
        varchar tenant_id FK
        json event_properties
        timestamp event_timestamp
    }

    product_page_views {
        bigint id PK
        bigint marketplace_product_id FK
        bigint customer_id FK
        varchar session_id
        int time_spent_seconds
        boolean added_to_cart
        boolean added_to_wishlist
        timestamp viewed_at
    }

    search_queries {
        bigint id PK
        bigint customer_id FK
        varchar session_id
        varchar search_query
        int results_count
        boolean has_results
        boolean converted_to_purchase
        bigint parent_search_id FK
        timestamp searched_at
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 9 — TENANT-CENTRAL SYNC
    %% ════════════════════════════════════════════════════════════════

    sync_queue_inbound {
        bigint id PK
        varchar tenant_id FK
        varchar syncable_type
        bigint tenant_syncable_id
        varchar action
        json payload
        varchar status
        tinyint retry_count
        tinyint max_retries
        varchar idempotency_key UK
        uuid batch_id
        timestamp received_at
    }

    sync_queue_outbound {
        bigint id PK
        varchar tenant_id FK
        varchar syncable_type
        bigint syncable_id
        varchar action
        json payload
        varchar status
        tinyint retry_count
        tinyint max_retries
        varchar idempotency_key UK
        uuid batch_id
        timestamp created_at
    }

    %% ════════════════════════════════════════════════════════════════
    %% DOMAIN 10 — INFRASTRUCTURE
    %% ════════════════════════════════════════════════════════════════

    cache {
        varchar key PK
        mediumtext value
        int expiration
    }

    cache_locks {
        varchar key PK
        varchar owner
        int expiration
    }

    jobs {
        bigint id PK
        varchar queue
        longtext payload
        tinyint attempts
        int available_at
    }

    job_batches {
        varchar id PK
        varchar name
        int total_jobs
        int pending_jobs
        int failed_jobs
        timestamp finished_at
    }

    failed_jobs {
        bigint id PK
        varchar uuid UK
        varchar queue
        longtext payload
        timestamp failed_at
    }

    system_notifications {
        bigint id PK
        varchar recipient_type
        bigint recipient_id
        varchar type
        varchar title
        text message
        boolean is_read
        boolean sent_via_email
        boolean sent_via_sms
        boolean sent_via_push
    }

    %% ════════════════════════════════════════════════════════════════
    %% RELATIONSHIPS
    %% ════════════════════════════════════════════════════════════════

    %% — Domain 1: Auth
    users ||--o{ sessions : "user_id"
    users ||--o{ otps : "user_id"

    %% — Domain 2: Permissions
    permissions ||--o{ role_has_permissions : "permission_id"
    roles ||--o{ role_has_permissions : "role_id"
    roles ||--o{ model_has_roles : "role_id"
    permissions ||--o{ model_has_permissions : "permission_id"

    %% — Domain 3: Tenancy
    tenants ||--o{ domains : "tenant_id"
    tenants ||--|| business_details : "tenant_id"
    tenants ||--|| tenant_profiles : "tenant_id"
    tenants ||--o{ business_subscriptions : "tenant_id"
    tenants ||--o{ tenant_delivery_zones : "tenant_id"
    tenants ||--o{ tenant_category_mappings : "tenant_id"
    tenants ||--o{ tenant_brand_mappings : "tenant_id"
    business_types ||--o{ business_categories : "business_type_id"
    business_types ||--o{ business_details : "business_type_id"
    business_categories ||--o{ business_details : "business_category_id"
    subscription_plans ||--o{ business_subscriptions : "subscription_plan_id"

    %% — Domain 4: Customers
    users ||--|| marketplace_customers : "user_id"
    marketplace_customers ||--o{ customer_addresses : "customer_id"

    %% — Domain 5: Catalog
    marketplace_categories |o--o{ marketplace_categories : "parent_id"
    marketplace_categories ||--o{ marketplace_products : "marketplace_category_id"
    marketplace_categories ||--o{ tenant_category_mappings : "marketplace_category_id"
    marketplace_categories |o--o{ customer_journey_events : "marketplace_category_id"
    marketplace_brands ||--o{ marketplace_products : "marketplace_brand_id"
    marketplace_brands ||--o{ tenant_brand_mappings : "marketplace_brand_id"
    tenants ||--o{ marketplace_products : "tenant_id"

    %% — Domain 6: Orders & Payments
    marketplace_customers ||--o{ marketplace_orders : "customer_id"
    customer_addresses |o--o{ marketplace_orders : "delivery_address_id"
    tenants ||--o{ marketplace_orders : "tenant_id"
    marketplace_orders ||--o{ marketplace_order_items : "order_id"
    marketplace_orders ||--o{ marketplace_order_payments : "order_id"
    marketplace_orders ||--|| marketplace_order_deliveries : "order_id"
    marketplace_products ||--o{ marketplace_order_items : "marketplace_product_id"
    marketplace_customers |o--o{ shopping_carts : "customer_id"
    shopping_carts ||--o{ shopping_cart_items : "cart_id"
    marketplace_products ||--o{ shopping_cart_items : "marketplace_product_id"
    shopping_carts ||--o{ checkout_sessions : "cart_id"
    marketplace_customers |o--o{ checkout_sessions : "customer_id"
    marketplace_orders |o--o{ shopping_carts : "converted_order_id"
    marketplace_orders |o--o{ checkout_sessions : "completed_order_id"

    %% — Domain 7: Reviews
    marketplace_products ||--o{ product_reviews : "marketplace_product_id"
    marketplace_customers ||--o{ product_reviews : "customer_id"
    marketplace_orders |o--o{ product_reviews : "order_id"
    tenants ||--o{ merchant_reviews : "tenant_id"
    marketplace_customers ||--o{ merchant_reviews : "customer_id"
    marketplace_orders ||--|| merchant_reviews : "order_id"
    marketplace_customers ||--o{ review_votes : "customer_id"
    marketplace_customers ||--o{ review_flags : "customer_id"

    %% — Domain 8: Discovery & Analytics
    marketplace_customers ||--o{ wishlists : "customer_id"
    marketplace_products ||--o{ wishlists : "marketplace_product_id"
    marketplace_customers |o--o{ customer_journey_events : "customer_id"
    marketplace_products |o--o{ customer_journey_events : "marketplace_product_id"
    tenants |o--o{ customer_journey_events : "tenant_id"
    marketplace_products ||--o{ product_page_views : "marketplace_product_id"
    marketplace_customers |o--o{ product_page_views : "customer_id"
    marketplace_customers |o--o{ search_queries : "customer_id"
    search_queries |o--o{ search_queries : "parent_search_id"

    %% — Domain 9: Sync
    tenants ||--o{ sync_queue_inbound : "tenant_id"
    tenants ||--o{ sync_queue_outbound : "tenant_id"
```

---

## Table Reference

### Domain 1 — Identity & Auth

| # | Table | Purpose |
|---|-------|---------|
| 1 | `users` | Core authentication record storing credentials, account type (`admin`/`customer`), and basic profile for every platform user. |
| 2 | `password_reset_tokens` | Temporary email-keyed tokens issued to verify password-reset requests. |
| 3 | `sessions` | Server-side session store; records active web sessions with IP address, user-agent string, and serialised payload. |
| 4 | `personal_access_tokens` | Sanctum API bearer tokens supporting scoped, expirable authentication for mobile apps and API integrations. |
| 5 | `otps` | Time-limited one-time passcodes (login, phone verification) bound to a user with per-attempt tracking and hard expiry. |
| 6 | `notifications` | Laravel polymorphic in-app notification log; stores read/unread alerts for any notifiable model instance. |

### Domain 2 — Permissions (Spatie Laravel-Permission)

| # | Table | Purpose |
|---|-------|---------|
| 7 | `permissions` | Named, guard-scoped permission atoms that represent individual access rights on the platform. |
| 8 | `roles` | Named, guard-scoped role definitions grouping sets of permissions for assignment to model instances. |
| 9 | `role_has_permissions` | Pivot linking roles to their granted permission set; the bridge between the two Spatie entities. |
| 10 | `model_has_roles` | Polymorphic pivot assigning one or more roles to any Eloquent model (users, customers, etc.). |
| 11 | `model_has_permissions` | Polymorphic pivot granting direct permissions (bypassing roles) to any Eloquent model instance. |

### Domain 3 — Tenancy & Subscriptions

| # | Table | Purpose |
|---|-------|---------|
| 12 | `tenants` | Root tenant registry; each row identifies a merchant/store by UUID with an arbitrary JSON metadata blob. |
| 13 | `domains` | Maps custom subdomains or vanity domains to their owning tenant, driving tenant resolution on every request. |
| 14 | `business_types` | Top-level merchant classification taxonomy (e.g. Retail, Restaurant, Pharmacy). |
| 15 | `business_categories` | Second-level merchant sub-classification nested inside a business type (e.g. Electronics within Retail). |
| 16 | `business_details` | Comprehensive merchant profile for a tenant: branding, contact info, operating hours, social links, verification status, and full onboarding lifecycle. |
| 17 | `subscription_plans` | Platform subscription tier definitions specifying pricing, billing cycle, currency, and JSON-encoded feature flags. |
| 18 | `business_subscriptions` | Tracks the current and historical subscription state per tenant, including trial windows, payment references, and cancellation metadata. |
| 19 | `tenant_profiles` | Cached aggregated stats (ratings, order counts, revenue, product counts) for a tenant's marketplace footprint, updated by background jobs. |
| 20 | `tenant_delivery_zones` | Delivery coverage areas synced from a tenant's POS system, with fee tiers per delivery method and free-delivery thresholds. |

### Domain 4 — Marketplace Customers

| # | Table | Purpose |
|---|-------|---------|
| 21 | `marketplace_customers` | Marketplace-specific customer profile extending a `users` row with phone, loyalty status, and marketing-consent preferences. |
| 22 | `customer_addresses` | Saved delivery addresses per customer, supporting home, work, and labelled custom types with optional GPS coordinates. |

### Domain 5 — Marketplace Catalog

| # | Table | Purpose |
|---|-------|---------|
| 23 | `marketplace_categories` | Self-referencing hierarchical product taxonomy for the central marketplace, with featured and display-order controls. |
| 24 | `marketplace_brands` | Curated brand registry shared across all tenant product listings on the marketplace. |
| 25 | `tenant_category_mappings` | Translates a tenant's POS product categories to canonical marketplace categories, with optional AI confidence scoring and manual verification flag. |
| 26 | `tenant_brand_mappings` | Translates a tenant's POS brand records to canonical marketplace brands, with optional AI confidence scoring and manual verification flag. |
| 27 | `marketplace_products` | Centralised product catalogue synced from tenant POS systems; one row per product, variant, or bundle, carrying stock, pricing, imagery, and sync metadata. |

### Domain 6 — Marketplace Orders & Payments

| # | Table | Purpose |
|---|-------|---------|
| 28 | `marketplace_orders` | Top-level order header capturing customer, merchant, totals, fulfilment type, and the full reservation → payment lifecycle state machine. |
| 29 | `marketplace_order_items` | Line items for an order, preserving a product snapshot (name, SKU, price, UOM, taxes) at the moment of purchase. |
| 30 | `marketplace_order_payments` | Payment attempt and outcome records per order, supporting M-Pesa, card, cash, and bank transfer, with refund and failure tracking. |
| 31 | `marketplace_order_deliveries` | One-to-one delivery tracking record per order: courier assignment, status progression, timestamps, GPS coordinates, and proof-of-delivery. |
| 32 | `shopping_carts` | Active or historical cart session per customer or anonymous visitor, tracking the full conversion and abandonment lifecycle with recovery signals. |
| 33 | `shopping_cart_items` | Individual product line items inside a shopping cart with quantity and price captured at time of addition. |
| 34 | `checkout_sessions` | Step-by-step checkout funnel state tracker per cart, recording step completion flags, abandonment point, and the resulting completed order. |

### Domain 7 — Reviews & Moderation

| # | Table | Purpose |
|---|-------|---------|
| 35 | `product_reviews` | Customer star ratings and written reviews for a specific marketplace product, with moderation workflow, merchant response, and verified-purchase flag. |
| 36 | `merchant_reviews` | Customer multi-dimensional reviews of the overall merchant experience per order (overall, product quality, delivery, service ratings). |
| 37 | `review_votes` | Polymorphic helpful/not-helpful votes cast by customers against either product or merchant reviews. |
| 38 | `review_flags` | Polymorphic abuse or spam flags raised by customers against either product or merchant reviews, feeding the moderation queue. |

### Domain 8 — Discovery & Analytics

| # | Table | Purpose |
|---|-------|---------|
| 39 | `wishlists` | Saved product bookmarks per customer with desired quantity and price-at-addition for future price-drop alerting. |
| 40 | `customer_journey_events` | Granular behavioural event log (page views, searches, add-to-cart, checkout steps) supporting analytics pipelines and personalisation. |
| 41 | `product_page_views` | Per-product page engagement metrics capturing time spent, scroll depth, and micro-conversion signals (add-to-cart, add-to-wishlist) per session. |
| 42 | `search_queries` | Log of marketplace search terms with result counts, click-through counts, purchase conversion, and query-refinement chaining via `parent_search_id`. |

### Domain 9 — Tenant-Central Sync

| # | Table | Purpose |
|---|-------|---------|
| 43 | `sync_queue_inbound` | Durable job queue for inbound sync payloads (products, inventory, delivery zones) flowing from tenant POS systems into the central platform, with retry, backoff, and idempotency. |
| 44 | `sync_queue_outbound` | Durable job queue for outbound sync payloads (orders, payments, delivery updates, reviews) flowing from central to tenant POS systems, with delivery acknowledgement tracking. |

### Domain 10 — Infrastructure

| # | Table | Purpose |
|---|-------|---------|
| 45 | `cache` | Laravel database-backed cache store; key-value pairs with integer TTL expiration for general-purpose caching. |
| 46 | `cache_locks` | Atomic lock primitives for coordinating concurrent processes through the database cache driver. |
| 47 | `jobs` | Laravel queue job store; holds pending and reserved background job payloads routed across named queues. |
| 48 | `job_batches` | Tracks batched job group status (total, pending, and failed counts) for bulk queue processing with completion events. |
| 49 | `failed_jobs` | Permanent log of all jobs that exhausted their retry budget, preserving the full payload and exception for post-mortem debugging. |
| 50 | `system_notifications` | Platform-generated operational notifications dispatched to staff or system recipients via email, SMS, and push channels with delivery status tracking. |

---

_Generated: Poachy Central DB · 10 domains · 50 tables_
