# Central Database Schema Reference

This document provides an overview of the **central marketplace database structure**. It summarizes all tables in the central database, their purpose, and their role in managing the multi-tenant marketplace platform.

---

## Table of Contents

-   [Tenant Management](#tenant-management)
-   [Marketplace Customers](#marketplace-customers)
-   [Product Catalog & Taxonomy](#product-catalog--taxonomy)
-   [Orders & Transactions](#orders--transactions)
-   [Reviews & Ratings](#reviews--ratings)
-   [Synchronization](#synchronization)
-   [Customer Behavior & Analytics](#customer-behavior--analytics)
-   [Notifications](#notifications)

---

## Tenant Management

### **tenants**

Core tenant registry with UUID-based identification. Uses Stancl/Tenancy package's simplified structure with flexible JSON data storage for tenant configuration.

### **domains**

Maps domain names to tenants for subdomain-based tenant identification (e.g., `electronics-shop.poachy.com`).

### **business_types**

Top-level business classification (e.g., "Retail & Consumer Goods", "Food & Beverage", "Services"). Provides first-level categorization for merchant businesses.

### **business_categories**

Specific business categories under business types (e.g., "Supermarket", "Electronics Shop", "Restaurant"). Helps customers find relevant merchants and enables platform analytics.

### **business_details**

Comprehensive merchant/tenant business profile including:

-   Business information (name, description, logo, banner)
-   Contact details (email, phone, contact person)
-   Location (address, city, county)
-   Operating hours and delivery information (JSON)
-   Ratings and verification status
-   Settings (currency, tax rates, payment methods)
-   Social media links
-   Status tracking (active, inactive, suspended, pending)

Used for merchant storefront pages and marketplace merchant listings.

### **subscription_plans**

Defines available subscription tiers (Free, Basic, Premium, Enterprise) with:

-   Pricing and billing cycles
-   Feature limits (JSON structure for flexibility)
-   Plan descriptions and display settings

Foundation for monetizing the platform through merchant subscriptions.

### **business_subscriptions**

Tracks individual merchant subscription status including:

-   Current plan and subscription period
-   Payment information and status
-   Auto-renewal settings
-   Trial period tracking
-   Cancellation details

Links tenants to their active subscription plans and manages billing lifecycle.

### **roles & permissions**

Standard _Spatie Permission_ tables configured for central database:

-   `roles`: Platform-level roles (Super Admin, Support, etc.)
-   `permissions`: Central system permissions
-   `model_has_roles`: User-role assignments
-   `model_has_permissions`: Direct permission grants
-   `role_has_permissions`: Role-permission mappings

Manages access control for platform administrators and central system operations.

---

## Marketplace Customers

### **marketplace_customers**

Central customer accounts for online marketplace shopping. Stores:

-   Basic information (name, email, phone, password)
-   Profile details (date of birth, gender, profile picture)
-   Account verification status (email, phone)
-   Marketing preferences
-   Security details (last login, remember token)

**Important**: These are marketplace-specific customers, distinct from tenant-specific customers in POS systems.

### **customer_addresses**

Multiple delivery addresses per marketplace customer with:

-   Address details (type, label, recipient info)
-   Location information (address line, building, city, county, coordinates)
-   Delivery instructions for couriers
-   Default address flag

Supports flexible delivery options and address book functionality.

---

## Product Catalog & Taxonomy

### **marketplace_categories**

Standardized, centralized category taxonomy for the entire marketplace. Features:

-   Hierarchical structure (parent-child relationships)
-   Display customization (icons, banners, ordering)
-   SEO optimization (meta titles, descriptions)
-   Featured category support

Provides consistent browsing experience across all merchants while allowing merchant-specific naming.

### **marketplace_brands**

Centralized brand registry for marketplace-wide brand filtering and browsing. Includes:

-   Brand information (name, slug, description, logo)
-   Display settings (featured status, ordering)

Enables customers to browse products by recognized brands across multiple merchants.

### **tenant_category_mappings**

Maps tenant-specific product categories to standardized marketplace categories:

-   Links tenant's category ID to marketplace category
-   Stores tenant's original category name (denormalized)
-   Tracks auto-mapping confidence scores
-   Verification status by merchant

**Purpose**: Allows merchants to use their own category names while maintaining marketplace consistency. Example: Tenant's "Mobile Phones" → Marketplace's "Smartphones".

### **tenant_brand_mappings**

Maps tenant-specific brands to standardized marketplace brands:

-   Links tenant's brand ID to marketplace brand
-   Stores tenant's original brand name (denormalized)
-   Auto-mapping confidence and verification tracking

Enables brand standardization while preserving merchant terminology.

### **marketplace_products**

Aggregated product catalog synced from all tenant databases. Denormalized for performance:

-   Source tracking (tenant_id, tenant product/variant/bundle IDs)
-   Product details (name, slug, description, SKU)
-   **Dual categorization**:
    -   Tenant's original category/brand names (for display authenticity)
    -   Mapped marketplace category/brand IDs (for filtering/browsing)
-   Pricing and UOM information
-   Cached inventory status (synced from tenants)
-   Media (images)
-   Performance metrics (views, orders, ratings)
-   Visibility and sync status

**Key Design**: Maintains both tenant-specific naming AND marketplace standardization for optimal user experience.

---

## Orders & Transactions

### **marketplace_orders**

Core order records for marketplace purchases:

-   Customer and delivery address references
-   Merchant (tenant) identification
-   Store location for pickup orders (nullable delivery_address_id supports pickup)
-   Order amounts (subtotal, tax, discount, delivery fee, total)
-   Fulfillment type (delivery vs. pickup)
-   Order status tracking
-   Customer/merchant notes
-   Cancellation tracking

**Note**: Split into multiple normalized tables for clean data separation.

### **marketplace_order_items**

Line items for marketplace orders with snapshot data:

-   Product references (marketplace and tenant IDs)
-   Item details at order time (name, SKU, variant)
-   Quantity and UOM information
-   Pricing snapshot (prevents historical data loss)
-   Per-item fulfillment status

Maintains order integrity even if products change or are deleted.

### **marketplace_order_payments**

Dedicated payment tracking table (normalized from orders):

-   Payment method and provider details
-   Payment status lifecycle (pending → processing → completed/failed)
-   Transaction references (M-PESA codes, gateway IDs)
-   Timing (initiated, completed, failed)
-   Error tracking for failed payments
-   Refund tracking (amount, timestamp, reference)
-   Payment metadata (gateway-specific data)

Supports multiple payment attempts and complex payment flows.

### **marketplace_order_deliveries**

Comprehensive delivery tracking (normalized from orders):

-   Delivery method (standard, express, scheduled)
-   Detailed status tracking (10+ states from pending to delivered)
-   Courier information (company, driver, phone, tracking)
-   Timing (estimated and actual pickup/delivery)
-   Delivery proof (signature, photo, OTP)
-   Issue tracking (notes, problems, attempts)
-   Real-time location updates (latitude, longitude)

**Delivery Fee Calculation**: Stored in `marketplace_orders.delivery_fee`. Calculated based on:

1. Distance (customer address → merchant store location)
2. Delivery method (standard vs. express)
3. Order value (free delivery thresholds)
4. Merchant delivery settings (from `business_details.delivery_info` JSON)

Calculation happens at checkout before order creation.

---

## Reviews & Ratings

### **product_reviews**

Customer reviews for marketplace products:

-   Product and customer references
-   Order verification (verified purchase flag)
-   Review content (rating 1-5, title, text, images)
-   Moderation workflow (pending → approved/rejected)
-   Helpful votes tracking
-   Merchant response capability

Builds trust and provides product feedback.

### **merchant_reviews**

Overall merchant/store ratings:

-   Multi-dimensional ratings (overall, product quality, delivery, service)
-   Review text
-   Order-based (one review per order)
-   Moderation workflow

Affects merchant visibility and credibility on the marketplace.

---

## Synchronization

### **sync_queue_inbound**

**Direction**: Tenant DB → Central DB

Receives synchronization requests FROM tenant databases including:

-   Products, variants, bundles
-   Inventory updates
-   Price changes
-   Product activation/deactivation

**Processing Flow**:

1. Tenant creates entry in their `sync_queue_outbound`
2. Tenant sync job sends to central API/webhook
3. Central creates `sync_queue_inbound` entry
4. Central sync job processes:
    - Maps categories/brands to marketplace taxonomy
    - Updates/creates `marketplace_products`
    - Handles conflicts and deduplication

**Key Fields**:

-   Polymorphic syncable (type + tenant_id)
-   Action types (create, update, delete, activate, deactivate)
-   Priority-based processing (1=critical inventory, 10=bulk)
-   Retry mechanism with exponential backoff
-   Locking for concurrent processing prevention
-   Idempotency keys for deduplication
-   Batch processing support

### **sync_queue_outbound**

**Direction**: Central DB → Tenant DB

Sends synchronization data TO tenant databases including:

-   Marketplace orders
-   Payment confirmations
-   Delivery updates
-   Product reviews
-   Merchant reviews

**Processing Flow**:

1. Customer places order on marketplace
2. Central creates `marketplace_orders`
3. Central creates `sync_queue_outbound` entry
4. Central sync job sends to tenant webhook/API
5. Tenant processes and creates local records:
    - Customer (if new)
    - Sale and sale_items
    - Sale_payments
    - Inventory_movements (deduct stock)
6. Tenant acknowledges receipt
7. Central marks as completed

**Key Fields**:

-   Syncable type (MarketplaceOrder, Payment, Review)
-   Action types (create, update, payment_confirmed, delivery_update)
-   Higher max retries (5) for critical data
-   Acknowledgment tracking (delivered → acknowledged → completed)
-   Tenant response storage

-   **Tenant**: `sync_queue_outbound` (send to central)
-   **Central**: `sync_queue_inbound` (receive from tenants) + `sync_queue_outbound` (send to tenants)

---

## Customer Behavior & Analytics

### **shopping_carts**

Tracks shopping cart sessions for logged-in and guest users:

-   Customer or session identification
-   Merchant (tenant) association
-   Cart status (active, abandoned, converted, expired)
-   Abandonment tracking (when, recovery attempts)
-   Device and browser metadata
-   Conversion tracking (linked order)

Foundation for abandoned cart recovery campaigns.

### **shopping_cart_items**

Line items within shopping carts:

-   Cart and product references
-   Product details snapshot
-   Quantity, UOM, pricing
-   Price comparison (added price vs. current price)
-   Timing (when added, last updated)

Enables cart persistence and price change notifications.

### **checkout_sessions**

Detailed checkout funnel tracking:

-   Progress through checkout steps (cart → shipping → payment → review)
-   Per-step timing and completion tracking
-   Abandonment detection (where customers drop off)
-   Device and browser information
-   Exit survey/reason capture
-   Conversion tracking

**Purpose**: Identify friction points in checkout flow for optimization (e.g., "60% abandon at payment step").

### **customer_journey_events**

Comprehensive event stream for customer behavior:

-   Wide range of event types:
    -   Browsing: page_view, product_view, product_list_view
    -   Search: search, filter_used
    -   Cart: add_to_cart, remove_from_cart, add_to_wishlist
    -   Checkout: checkout_started, checkout_step_completed
    -   Post-purchase: purchase, review_written, merchant_followed
-   Event context (page URL, referrer, related entities)
-   Rich event properties (JSON with type-specific data)
-   Device, location, and timing data
-   Session grouping (session_uuid, sequence tracking)

**Purpose**: Complete customer journey reconstruction for:

-   Personalization
-   Behavior analysis
-   Conversion funnel optimization
-   Marketing attribution

### **product_page_views**

Specialized product engagement tracking:

-   Product and customer/session identification
-   Referrer source tracking (search, category, home)
-   Engagement metrics:
    -   Time spent
    -   Scroll depth (description, reviews)
    -   Image interactions
    -   Cart/wishlist actions
-   Device information

**Purpose**: Product-specific analytics for merchandising decisions (e.g., "High views but low add-to-cart = pricing issue?").

### **search_queries**

Search behavior and performance tracking:

-   Search terms and result counts
-   Zero-result query identification
-   Applied filters (JSON)
-   Result interactions (clicks, add-to-cart, purchases)
-   Search refinement chains (parent_search_id)

**Purpose**:

-   Identify popular searches
-   Fix zero-result queries (add missing products)
-   Improve search relevance
-   Understand search-to-purchase patterns

---

## Notifications

### **system_notifications**

Multi-channel notification system for customers and merchants:

-   Polymorphic recipient (marketplace_customer or tenant)
-   Notification types:
    -   Customer: order_update, payment_received, abandoned_cart, price_drop, back_in_stock
    -   Merchant: product_review, low_stock, subscription_expiring
-   Notification content (title, message, structured data)
-   Actionable notifications (URL, label)
-   Read status tracking
-   Multi-channel delivery:
    -   Email (with timestamp)
    -   SMS (with timestamp)
    -   Push notifications (with timestamp)

Centralizes all system notifications with delivery tracking across channels.

---

## Additional Tables

### **wishlists**

Customer product wishlists:

-   Customer and product references
-   Personal notes
-   Desired quantity
-   Unique constraint (one product per customer wishlist)

Enables "save for later" functionality and can trigger back-in-stock notifications.

---

## Key Design Patterns

### **Denormalization for Performance**

Tables like `marketplace_products` store redundant data (tenant category names, merchant names) to:

-   Avoid expensive joins during browsing
-   Maintain historical accuracy
-   Enable fast filtering and searching

### **Dual Taxonomy System**

Products maintain BOTH:

-   **Tenant names** (merchant's terminology) - for authentic display
-   **Marketplace IDs** (standardized taxonomy) - for filtering/browsing

This balances consistency with merchant autonomy.

### **Normalized Order Structure**

Orders split into:

-   `marketplace_orders` - core order data
-   `marketplace_order_items` - line items
-   `marketplace_order_payments` - payment lifecycle
-   `marketplace_order_deliveries` - delivery tracking

Prevents data bloat and enables independent evolution of each concern.

### **Event-Driven Sync**

Sync queues enable:

-   Asynchronous processing (no blocking)
-   Retry mechanisms (reliability)
-   Priority-based processing (critical first)
-   Deduplication (idempotency)
-   Audit trails (complete history)

### **Session-Based Analytics**

Customer behavior tracking works for:

-   Logged-in users (customer_id)
-   Guest users (session_id)
-   Cross-device journeys (session_uuid)

Enables comprehensive analytics without requiring authentication.

---

## Database Relationships

### **Primary Flows**

1. **Tenant → Central (Product Sync)**:

    ```
    Tenant: products → sync_queue_outbound
                     ↓
    Central: sync_queue_inbound → marketplace_products
    ```

2. **Central → Tenant (Order Sync)**:

    ```
    Central: marketplace_orders → sync_queue_outbound
                                ↓
    Tenant: [webhook] → sales + inventory_movements
    ```

3. **Customer Journey**:
    ```
    customer_journey_events → product_page_views
                           → search_queries
                           → shopping_carts
                           → checkout_sessions
                           → marketplace_orders
                           → product_reviews
    ```

---
