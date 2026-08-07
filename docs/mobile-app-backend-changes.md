# Backend changes for the Poachy Mobile (Flutter) app

This document lists backend corrections and additions discovered while building
and live-testing the **Poachy Mobile** Merchant POS app (Flutter) against this
backend (verified end-to-end on iOS against a seeded `demo` tenant on
2026‑08‑06: login → OTP → POS → live `sales/calculate` → `POST /sales` →
receipt, plus product create + stock + business‑type‑aware defaults).

Each item lists **where**, **why it matters to the app**, and a **suggested
fix**. Priorities: **P1** = breaks a flow / returns 500; **P2** =
client‑blocking or inconsistent; **P3** = needed for the mobile roadmap
(offline, push, onboarding).

---

## P1 — Bugs (return 500 / break a flow)

### 1. Walk‑in sale returns 500 — `customer_id` read without null‑coalesce  ✅ already fixed in working tree
- **Where:** `app/Services/Tenant/Sales/SaleService.php` → `createSale()` (was ~line 113).
- **Why:** `customer_id` is nullable in `CreateSaleRequest`, so a walk‑in POS sale legitimately omits it. The service read `$data['customer_id']` directly → `Undefined array key "customer_id"` → **HTTP 500** ("Failed to create sale…") on every no‑customer sale.
- **Repro:** `POST /api/v1/tenant/sales` with `{store_id, items, payments}` and **no** `customer_id`.
- **Fix:** `$customerId = $data['customer_id'] ?? null;` and use it throughout. **This is already applied in the working tree — please commit it.**
- **App note:** the app now sends `customer_id: null` explicitly as a belt‑and‑suspenders workaround, but the backend should tolerate an absent key.

### 2. Stock adjustment 500s when `uom_id` isn't the product's base UOM
- **Where:** `app/Services/Tenant/Inventory/InventoryMovementService.php` → `convertToBaseUom()` (~line 369).
- **Why:** For a product with no extra UOM mappings, any `uom_id` other than the product's `base_uom_id` hits `ProductUom::…->firstOrFail()`, which throws → controller returns **HTTP 500** "Failed to record adjustment: …". This is a validation condition, not a server error.
- **Fix:** Return a clear **422** (e.g. "No conversion from unit X to the product's base unit") instead of a 500, or fall back to base UOM when no mapping exists.
- **App note:** the app currently forces `uom_id == base_uom_id` for freshly created products to avoid this.

---

## P2 — Client‑blocking / consistency

### 3. `GET /tenant/auth/me` omits `roles` (and tenant), so the app can't role‑gate after a token restore
- **Where:** `TenantAuthController::me()` + `app/Http/Resources/Tenant/Auth/TenantUserResource.php` (uses `whenLoaded('roles')`).
- **Why:** `login`/`verify-otp` include `roles`, but `me()` does not eager‑load them, so on app relaunch (token restored from secure storage → `me()`), the client has **no roles** and cannot hide owner/manager‑only actions (e.g. Add Product, refunds) from cashiers. Without this the app either shows those actions to everyone and relies on 403s, or forces a fresh OTP login.
- **Fix:** Eager‑load `roles` in `me()` (`$user->load('roles')`), and ideally include the tenant summary (`{id, name, domains, has_business_details}`) so the app can rebuild full session state without re‑authenticating.

### 4. Product image URLs are built from static `APP_URL/storage`, not per‑tenant storage — verify they resolve
- **Where:** `ProductResource` / `ProductListResource` / receipt builder → `Storage::disk('public')->url($path)` (and `asset('storage/'.$path)`), i.e. `APP_URL/storage/...`.
- **Why:** Files are written to **per‑tenant suffixed** storage (`config/tenancy.php` suffixes `storage_path`), but the emitted `*_url` host+prefix come from the static central `APP_URL/storage`. On a real tenant subdomain these URLs may **404**. In the app, product images currently fall back to a placeholder.
- **Fix:** Serve tenant assets per‑tenant (Stancl asset route) or move the `public` disk to S3 and return absolute S3 URLs. At minimum, verify end‑to‑end on a real tenant that product/receipt image URLs load.

### 5. Product barcode: only `sku` is searchable; `product_barcodes` exists but is unwired
- **Where:** `products` table has `sku` only; `product_barcodes` table exists (migration) but has **no model / relation / route / service** (grep for `ProductBarcode`/`barcodeable` → 0 app hits). Product search matches name/sku/description only.
- **Why:** POS barcode scanning can only resolve a scan by **SKU** via `GET /products?search=`. A manufacturer EAN that differs from the SKU won't resolve.
- **Fix:** Add a `barcode` field on products (or wire `product_barcodes`) and a fast lookup, e.g. `GET /api/v1/tenant/products/lookup?barcode=…`, indexed for till‑speed scanning.

### 6. Stale OpenAPI docblock on product create (misleads client devs)
- **Where:** `ProductController::store` OpenAPI docblock vs `StoreProductRequest`.
- **Why:** The docblock lists `tax_rate_id` as **required**, but `StoreProductRequest` doesn't accept it at all (a product is created with `tax_rate_id = null`; tax is set later via `PATCH /products/{uuid}/inventory`). This cost time during integration.
- **Fix:** Update the docblock to match the FormRequest (required set is `name, category_id, base_selling_price, base_uom_id, primary_image`; `product_type` defaults to `simple`).

### 7. Pagination envelope is inconsistent across list endpoints
- **Where:** e.g. `TenantUserController::index` uses custom `data.data` + `data.pagination`; `ProductController::index` uses `data.products` + `data.pagination`; `StoreController::index` uses a `ResourceCollection` → Laravel default `data.data` + `data.meta` + `data.links`.
- **Why:** The client must handle **three** different shapes for list responses. The app currently works around this by scanning for the first list‑valued key and reading `pagination` **or** `meta`.
- **Fix:** Standardize all list endpoints on one envelope (recommend the custom `{ data: [...], pagination: {...} }`).

---

## P3 — Additions the mobile roadmap needs

### 8. Idempotency key on `POST /sales` (offline queueing)
Accept and dedupe a client‑supplied idempotency key so a retried/queued offline sale can't double‑post. (Marketplace checkout already has `checkout_idempotency_key`; the POS sale path doesn't.)

### 9. Catalog delta‑sync endpoint (offline)
A snapshot + `updated_since` cursor for products/variants/prices/UOMs/tax/customers/promotions, so the app can cache the catalogue locally and sync incrementally. The current per‑resource, paginated reads are too heavy to mirror a full catalogue offline.

### 10. Push‑notification infrastructure (none exists today)
Broadcasting is `log`; there's no FCM/APNs, no device‑token store (only a `// TODO: Integrate FCM` stub in `app/Jobs/Tenant/SendNotificationJob.php`). Needs: a device‑token registration endpoint (per tenant user), a notification channel, and triggers (payment confirmed, new marketplace order to fulfil, low‑stock/expiry, shift reminders). Until then the app must poll (e.g. M‑Pesa status).

### 11. SMS OTP (currently email‑only)
OTP is delivered by email only; the Africa's Talking SMS channel is a commented‑out TODO (`CustomerOtpNotification`, `TenantOtpService`). A Kenyan mobile POS realistically needs SMS OTP.

### 12. Public "find my workspace" endpoint (tenant discovery)
There's no public way to resolve a workspace slug / tenant before login (the tenant search is `role:admin` only). The staff app must already know its subdomain. Consider a public, rate‑limited "find my workspace by email/slug" endpoint (or commit to white‑label builds / deep links).

### 13. POS token lifetime / refresh
Tenant Sanctum tokens are issued with a **1‑week** expiry and there is **no refresh flow**, so field staff must re‑do the full email‑OTP login weekly. Consider a refresh mechanism or a longer‑lived POS token.

### 14. Business‑type‑aware onboarding provisioning (for "choose business → auto setup")
- **Today:** `database/seeders/TenantDatabaseSeeder.php` seeds a **generic** set of product categories + all UOMs for **every** tenant regardless of business type (`ProductCategorySeeder`, `UnitsOfMeasureSeeder`). So a pharmacy tenant gets "Men's Fashion" categories, etc.
- **Ask:** Add **per‑business‑type templates** (starter categories, sensible default units, default tracking flags: batch/expiry, serial) applied at onboarding based on `business_details.business_type_id` / `business_category_id`, and **expose the template via an endpoint** so web and mobile share one source of truth.
- **App note:** the app currently encodes these vertical profiles **client‑side** as an interim (Pharmacy → batch+expiry on, Electronics → serial on, default unit `pcs`, etc.). Moving the template server‑side is the clean long‑term home.

---

## Local‑dev setup notes (not app bugs, but worth scripting/documenting)

### 15. A fresh `docker compose up` needs several manual steps
Encountered while bringing the stack up from scratch:
- No `.env` / `APP_KEY` by default — copy `.env.example` → `.env` and generate a key.
- Compose references an **external `dbtools` network** that must exist: `docker network create dbtools`.
- The runtime image has **no composer** and the code bind‑mount hides the image's `vendor/`, so `vendor/autoload.php` is missing → run `composer install` (e.g. via the `composer:2` image mounted on the project).
- **Seed order:** run central `php artisan db:seed` **before** `tenant:seed-demo` — the demo seeder fails at `seedCentralBusinessProfile` with `No query results for model [BusinessCategory]` if central reference data (business types/categories) isn't seeded first.

Consider a `make setup` / documented bootstrap so new devs (and the mobile team) don't hit these.

### 16. Outbound delivery‑zone sync errors in local
During seeding: `local.ERROR: Outbound delivery zone sync failed … cURL error 7: Failed to connect to localhost:80 … /central/sync/inbound/delivery-zone`. The tenant→central sync HTTP call targets an unreachable URL in local (`CENTRAL_API_URL`). Non‑blocking but noisy; point it at the in‑cluster host or guard it in local.

---

## Update (2026‑08‑07) — additional findings from batch/expiry + product‑create testing

### 17. P1 — Sale calculation NPEs on any product with no tax rate (blocks selling app‑created products)
- **Where:** `app/Services/Tenant/Sales/SaleCalculationService.php:204` `$taxRate = $product->taxRate;` then `:221` `'tax_rate_id' => $taxRate->id` and `:222` `'tax_rate_percentage' => $taxRate->rate` (same pattern at `:244/:263/:264` for variants, `:283` for bundles).
- **Why:** `$product->taxRate` is null whenever `products.tax_rate_id` is null — the case for **every product created via `POST /tenant/products`**, since create doesn't accept `tax_rate_id` (only settable later via `PATCH /products/{uuid}/inventory`). Result: `Attempt to read property "id" on null` → **HTTP 500** on both `POST /sales/calculate` and `POST /sales`. A freshly created product cannot be sold until a tax rate is attached.
- **Repro:** create a product via the API (no tax rate) → `POST /sales/calculate` with it → 500.
- **Fix:** null‑guard the tax rate — `$taxRate?->id`, `$taxRate?->rate ?? 0` — and treat a missing tax rate as **0% / tax‑exempt** (valid for zero‑rated goods, medicines, etc.). Same for the variant and bundle branches.
- **App note:** the app works around this by stamping the tenant **default** tax rate on every product it creates, but that forces tax onto items that may be exempt — the backend should tolerate a null tax rate.

### 18. DX — a PO can only order products already allocated to the store; consider a one‑shot "receive stock" endpoint
- **Where:** `POST /tenant/purchase-orders` returns `Product '…' is not allocated to this store. Only store products can be ordered.` unless the product has a `store_products` row for that store.
- **Why:** To receive batch/expiry stock for a new product, the app must chain **4 calls**: allocate to store (`POST /stores/{store}/products`) → create PO → send PO → receive goods (`POST /batches/receive`).
- **Suggestion:** a single `POST /tenant/products/{id}/receive-stock` taking `{store_id, quantity, unit_cost?, expiry_date?, supplier_id?}` that internally allocates (if needed), creates+sends a PO, and receives the batch. Would greatly simplify mobile receiving. (Not blocking — the app orchestrates the 4 calls today.)

---

*Generated from the Poachy Mobile integration effort. Questions → the mobile team. File/line references are approximate and may shift as the backend evolves.*
