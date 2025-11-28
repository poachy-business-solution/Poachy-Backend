# Tenant Database Schema Reference

This document provides an overview of the **multi-tenant database structure**.
It summarizes all tables included in the tenant migrations, their purpose, and their role in the system.

---

## Table of Contents

* [Core & Authentication](#core--authentication)
* [Store & Operations](#store--operations)
* [Product Management](#product-management)
* [Inventory Management](#inventory-management)
* [Procurement](#procurement)
* [Sales & Customers](#sales--customers)
* [Marketing & Promotions](#marketing--promotions)
* [Finance](#finance)

---

## Core & Authentication

### **users**

Stores tenant user accounts, including authentication details and profile information.

### **roles & permissions**

Standard *Spatie Permission* tables:

* `roles`
* `permissions`
* `model_has_roles`
* `role_has_permissions`
  Used to manage user access control within each tenant.

### **jobs & failed_jobs**

Laravel queue tables for managing background tasks specific to the tenant.

### **audit_logs**

Tracks user actions and system activities for accountability and debugging.

### **sync_queue_outbound**

Queue for synchronizing data from the tenant database to the central marketplace.
Includes status, priority, and payload information.

---

## Store & Operations

### **stores**

Represents physical or logical store locations with address, contacts, and operational status.

### **shifts**

Defines work shift templates (e.g., Morning, Evening) with start/end times.

### **shift_assignments**

Links users to specific shifts at specific stores.

### **shift_sales_summary**

Summaries of shift sales (cash in hand, expected amount, variances).

---

## Product Management

### **products**

Primary product definitions: name, type (standard, digital, service), and global settings.

### **product_variants**

Variations of a product (e.g., size, color).

### **product_categories**

Hierarchy of product categories.

### **product_brands**

Brand definitions for products.

### **units_of_measure (UOM)**

Defines units such as pcs, kg, liters.

### **uom_conversions**

Conversion relationships between units (e.g., 1 box = 12 pcs).

### **product_uoms**

Specific UOMs tied to products with unique pricing/barcodes.

### **product_bundles**

Groups of multiple items sold as a single bundle.

### **product_bundle_items**

Items that make up a product bundle.

### **store_products**

Pivot table managing product availability and store-specific prices.

### **product_barcodes**

Polymorphic barcodes for products, variants, or UOM levels.

### **product_price_history**

Tracks historical price changes for audit and analytics.

### **tax_rates**

Tax definitions applicable to products.

---

## Inventory Management

### **inventory**

Tracks stock levels per product/variant per store (on hand, reserved, reorder levels).

### **inventory_movements**

Ledger of all stock changes (sales, purchases, adjustments).

### **inventory_reservations**

Temporary stock holds (e.g., for online orders).

### **product_batches**

Batch-level tracking with expiry and manufacturing dates.

### **stock_alerts**

Low-stock notifications.

### **expiry_alerts**

Expiry date proximity notifications.

### **inventory_waste**

Records losses due to damage, expiry, or theft.

### **stock_transfers**

Header table for stock movements between stores.

### **stock_transfer_items**

Line items for stock transfer entries.

---

## Procurement

### **suppliers**

Vendor details.

### **purchase_orders**

Orders issued to suppliers for stock replenishment.

### **purchase_order_items**

Items included in purchase orders.

### **supplier_payments**

Records of payments made toward supplier invoices.

---

## Sales & Customers

### **sales**

Transaction header for receipts/invoices.

### **sale_items**

Product line items within a sale.

### **sale_payments**

Captures payment info (cash, M-Pesa, card, etc.).

### **sale_refunds**

Refund headers for returned items.

### **sale_refund_items**

Returned item details.

### **sales_daily_aggregates**

Daily precomputed sales summaries for performance reporting.

### **customers**

Customer profiles and contacts.

### **customer_groups**

Customer segmentation (VIP, wholesale, etc.).

### **loyalty_transactions**

Tracks loyalty points earned/redeemed.

### **customer_credit_transactions**

Tracks store credit usage and balances.

---

## Marketing & Promotions

### **coupons**

Configuration for discount codes.

### **coupon_usage**

Tracks coupon redemption.

### **promotions**

Automatic discount rules (e.g., BOGO deals).

### **promotion_usage**

Tracks promotions applied during sales.

### **coupon/promotion relations**

Associates coupons/promotions with products, categories, or brands
(e.g., `coupon_products`, `promotion_categories`).

---

## Finance

### **expenses**

Operational expense records (utilities, rent, etc.).

### **expense_categories**

Categories for expenses.

### **budgets**

Financial limits and planning for expense categories.

---