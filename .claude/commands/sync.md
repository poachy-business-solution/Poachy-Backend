# /sync — Poachy Sync Implementation Skill

You are implementing a sync endpoint for the **poachy** multi-tenant Laravel application.
Before writing any code, ask the user the following questions:

1. **What entity are you syncing?** (e.g. `ProductReview`, `StoreLocation`, `InventoryCount`)
2. **Which direction?** Tenant → Central (outbound), Central → Tenant (inbound), or both?
3. **What actions are needed?** (e.g. `create`, `update`, `delete`, `activate`, `deactivate`, `reserve`, `confirm`, `cancel`)

Once you have the answers, follow the rules and scaffold steps below exactly.

---

## Architecture Overview

### Tenant → Central (outbound)

```
Model Observer → SyncService → Event → Listener ($afterCommit=true)
→ SyncQueueOutbound (tenant DB) → Outbound Job (HTTP POST)
→ Central API Endpoint → Form Request → SyncController
→ SyncQueueInbound (central DB) → Inbound Job → Marketplace Model
```

### Central → Tenant (inbound)

```
Business trigger (order/payment/etc.) → OutboundSyncService.queue*()
→ SyncQueueOutbound (central DB) → Job (HTTP POST to tenant)
→ Tenant Endpoint → Tenant Inbound Job → Tenant model update
→ ACK back to central (POST /api/v1/central/sync/inbound/outbound-sync-ack)
```

---

## Queue Mapping

| Priority | Queue | Use For |
|----------|-------|---------|
| 1 (critical) | `sync-critical` | Payments, cancellations, order confirmations |
| 3 (high) | `sync-high` | Order reservations, product/variant/bundle sync |
| 5 (normal) | `sync-normal` | Reviews, ratings, non-urgent updates |
| 8–10 (low) | `sync-low` | Bulk operations, background reconciliation |

**Never** use `default` queue for sync jobs.

---

## Validation Layers — Three Distinct Checkpoints

Do NOT double-validate across layers. Each layer owns exactly one concern:

| Layer | Location | Responsibility |
|-------|----------|----------------|
| **Eligibility** | Observer (`created`, `updated`, `deleted`) | Should this entity sync at all? Check business rules (e.g. `is_available_online=true`). If not eligible, return silently — no event fired. |
| **DTO factory** | `{Entity}SyncDTO::fromModel()` | Data integrity safeguard. Throws `InvalidArgumentException` if required data is missing. Does NOT check business eligibility. |
| **Form Request** | `Inbound{Entity}SyncRequest` on the central API | Validates HTTP payload structure and required fields from the network. |

---

## Dual SyncQueueOutbound Models — Critical

There are **two** `SyncQueueOutbound` models. Using the wrong one corrupts data.

| Model | Namespace | DB | Used By |
|-------|-----------|-----|---------|
| `App\Models\Tenant\SyncQueueOutbound` | Tenant | tenant DB | Tenant-side listeners enqueuing syncs to central |
| `App\Models\SyncQueueOutbound` | Central | central DB | Central-side `OutboundSyncService` enqueuing syncs to tenants |

**Rule:** Tenant context code → `App\Models\Tenant\SyncQueueOutbound`. Central context code → `App\Models\SyncQueueOutbound`.

---

## Tenant → Central: 14 Scaffold Steps (follow in order)

### Step 1 — DTO `app/DataTransferObjects/Sync/{Entity}SyncDTO.php`

- Static `fromModel(Model $model): self` — DTO validation layer, throws `InvalidArgumentException` on bad data
- Static `fromArray(array $data): self` — for deserialization on the central inbound side
- `toArray(): array` — for serialization into the queue payload
- Must include `tenant_id` (from `tenant()->id`) and `idempotency_key`:
  `md5(tenantId . entityType . entityId . action . hash(ksort(payload)))`
- **Pattern:** `app/DataTransferObjects/Sync/ProductSyncDTO.php`

### Step 2 — Event `app/Events/Tenant/{Entity}MarketplaceSyncRequested.php`

- Stores: model instance, action string, priority int
- **Pattern:** `app/Events/Tenant/ProductMarketplaceSyncRequested.php`

### Step 3 — Listener `app/Listeners/Tenant/Enqueue{Entity}MarketplaceSync.php`

- **CRITICAL:** Declare `public bool $afterCommit = true;` — prevents jobs dispatching before DB transaction commits
- Check idempotency key on `App\Models\Tenant\SyncQueueOutbound` — skip if already exists
- Create `App\Models\Tenant\SyncQueueOutbound` record: `tenant_id`, `syncable_type`, `action`, `payload`, `priority`, `idempotency_key`, `expires_at` (now + 24h)
- Dispatch `ProcessOutbound{Entity}Sync` to the correct queue (use queue mapping table)
- **Race condition guard:** Catch `\Illuminate\Database\UniqueConstraintViolationException` **before** the general `\Exception` catch. Rollback, log INFO (not ERROR), and return silently — the concurrent process already inserted the record.

  ```php
  } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
      DB::rollBack();
      Log::info('{Entity} sync already queued by concurrent process, skipping', [...]);
  } catch (\Exception $e) {
      DB::rollBack();
      Log::error(...);
      throw $e;
  }
  ```

- **Pattern:** `app/Listeners/Tenant/EnqueueInventoryCountMarketplaceSync.php`

### Step 4 — Service `app/Services/Tenant/Sync/{Entity}SyncService.php`

- Public `syncToMarketplace(Model $model, string $action = 'create', int $priority = 5): void`
- Fires `{Entity}MarketplaceSyncRequested` event
- **Pattern:** `app/Services/Tenant/Sync/ProductSyncService.php`

### Step 5 — Observer `app/Observers/Tenant/{Entity}Observer.php`

- Hooks: `created()`, `updated()`, `deleted()`
- **Eligibility check here** — if not eligible, return silently (no event, no log)
- Inject `{Entity}SyncService` via constructor property promotion
- **Pattern:** `app/Observers/Tenant/ProductObserver.php`

### Step 6 — Outbound Job `app/Jobs/Tenant/ProcessOutbound{Entity}Sync.php`

- Timeout: 120s | tries: 3 | backoff: [60, 300, 900]
- Queue: per queue mapping table
- Acquire lock: `$syncQueue->acquireLock(getmypid())` — if fails, log and return silently
- HTTP POST to: `config('services.central_api.url') . '/api/v1/central/sync/inbound/{entity}'`
- Required headers: `X-Tenant-ID`, `X-Sync-Queue-ID`, `X-Idempotency-Key`, `Authorization: Bearer {token}`
- On failure: set `error_code` (NETWORK_ERROR | API_ERROR | TIMEOUT | UNKNOWN_ERROR), retry or mark permanently failed
- Always release lock in `finally` block
- **Pattern:** `app/Jobs/Tenant/ProcessOutboundProductSync.php`

### Step 7 — Form Request `app/Http/Requests/Central/Sync/Inbound{Entity}SyncRequest.php`

- Validate: `tenant_id`, `action`, `payload`, `idempotency_key`
- **Pattern:** `app/Http/Requests/Central/Sync/InboundProductSyncRequest.php`

### Step 8 — SyncController method `app/Http/Controllers/Api/Central/Sync/SyncController.php`

- Add `receive{Entity}Sync(Inbound{Entity}SyncRequest $request)` to the **existing** controller (never create a new one)
- Check idempotency key on `App\Models\SyncQueueInbound` — if exists, return cached result immediately
- Create `App\Models\SyncQueueInbound` record (central DB)
- Dispatch `ProcessInbound{Entity}Sync` job

### Step 9 — Inbound Job `app/Jobs/Central/ProcessInbound{Entity}Sync.php`

- Timeout: 180s | tries: 3 | backoff: [60, 300, 900]
- Status flow: `pending → processing → validating → mapping → syncing → completed`
- Acquire lock, validate DTO, use `MarketplaceMappingService` for category/brand mapping
- Find existing record by `(tenant_id, tenant_{entity}_id)` — update if exists, create if not
- Mark as `stale` if `expires_at` exceeded before processing
- Error codes: VALIDATION_ERROR | MAPPING_ERROR | DUPLICATE_ERROR | SYNC_ERROR | JOB_FAILED
- **`markAsCompleted()` — CRITICAL:** signature is `markAsCompleted(?int $centralRecordId = null, ?string $centralTable = null)`:
  - Job **creates** a new central record → `$syncQueue->markAsCompleted(centralRecordId: $model->id, centralTable: 'marketplace_products')`
  - Job **updates** an existing record (no new record) → `$syncQueue->markAsCompleted()` — no arguments, never pass an array
- **Pattern:** `app/Jobs/Central/ProcessInboundProductSync.php`

### Step 10 — Central Service `app/Services/Central/Sync/Marketplace{Entity}SyncService.php`

- `create{Entity}(DTO $dto): Model`
- `update{Entity}(Model $existing, DTO $dto): Model`
- `delete{Entity}(DTO $dto): void`
- **Pattern:** `app/Services/Central/Sync/MarketplaceSyncService.php`

### Step 11 — Central Migration (only if new entity type)

- File: `database/migrations/{timestamp}_create_marketplace_{entities}_table.php`
- Must include: `id`, `tenant_id` (FK → tenants), `tenant_{entity}_id`
- Unique constraint on `(tenant_id, tenant_{entity}_id)`
- Follow `MarketplaceProduct` column conventions
- Run: `vendor/bin/sail artisan migrate`

### Step 12 — Route `routes/central.php` (inside sync group ~lines 165-179)

```php
Route::post('inbound/{entity}', [SyncController::class, 'receive{Entity}Sync']);
```

### Step 13 — Register Observer

Add to `app/Providers/AppServiceProvider.php` (or `TenancyServiceProvider.php` if tenant-scoped).

### Step 14 — Register Event/Listener

Add mapping to `app/Providers/EventServiceProvider.php`.

---

## Central → Tenant: 6 Scaffold Steps (follow in order)

### Step 1 — Add action to enum `app/Enums/Central/OutboundSyncAction.php`

- TitleCase case name, with priority and label matching the queue tier

### Step 2 — Add method to OutboundSyncService `app/Services/Central/Marketplace/OutboundSyncService.php`

- `queue{EntityAction}(Model $model): void`
- Build idempotency key, create `App\Models\SyncQueueOutbound` (central DB) record
- Set: `tenant_id`, `syncable_type`, `action`, `payload`, `priority`, `idempotency_key`, `expires_at` (now + 24h)

### Step 3 — Trigger point

Call `OutboundSyncService->queue{EntityAction}()` from the correct existing job. Never call it inline from controllers or models.

Known trigger points:

| Action | Triggered From |
|--------|----------------|
| Order reservation | `app/Jobs/Central/ProcessCheckoutReservation.php` |
| Payment confirmed | `app/Jobs/Central/ProcessPaymentConfirmation.php` |
| Cancellation | `app/Jobs/Central/ProcessOrderCancellation.php` |
| Delivery update | Central delivery update job |
| New entity action | Identify the job/service that owns the business event, call from there |

### Step 4 — Verify ACK route exists

Check `routes/central.php` for:

```php
Route::post('inbound/outbound-sync-ack', [SyncController::class, 'acknowledgeOutboundSync']);
```

If missing, add it **before** implementing the tenant inbound job.

### Step 5 — Tenant Inbound Job `app/Jobs/Tenant/ProcessInbound{EntityAction}Sync.php`

- Timeout: 60–120s (tune per operation) | tries: 10 | backoff: [60, 120, 300, 600, ...]
- Queue: `sync-critical` for reservation/payment/cancellation; `sync-high` for others
- **Idempotency first:** if already processed, return existing result — do NOT throw or error
- Perform business logic (create reservation, sale, release inventory, etc.)
- Must ACK back to central on both success and failure:
  ```
  POST /api/v1/central/sync/inbound/outbound-sync-ack
  Headers: Authorization: Bearer {config('services.central_api.token')}
  Payload: { outbound_sync_id, status: 'completed'|'failed', reason?, ...result }
  ```
- **Pattern:** `app/Jobs/Tenant/ProcessInboundOrderSync.php`

### Step 6 — Register tenant inbound route (if new endpoint needed)

Add to `routes/tenant.php`.

---

## Cross-Cutting Rules

### Idempotency

- Every sync record MUST have a unique `idempotency_key`
- Format: `md5(tenant_id . entityType . entityId . action . hash(ksort(payload)))`
- Always check for existing key before inserting — return cached result if found
- Store the response on the queue record for replay

### Authentication

- Tenant → Central: Bearer token from `config('services.central_api.token')`
- Always pass: `X-Tenant-ID`, `X-Sync-Queue-ID`, `X-Idempotency-Key`

### Tenant Context in Jobs

- Tenant-side outbound jobs: `tenant()` helper is available
- Central-side inbound jobs: use `tenant_id` from payload only; never call `tenant()`
- Never mix DB connections — tenant models use tenant connection, central models use central

### Known Eligibility Requirements

- **Products:** `is_available_online=true`, `online_price>0`, `category_id`, `base_uom_id`, `tax_rate_id`
- **Variants:** `is_active=true`, `online_price>0`, parent product must pass product checks
- **Bundles:** `is_available_online=true`, `is_active=true`, `online_price>0`, `base_uom_id`, `tax_rate_id`, min 2 active items
- **InventoryCount:** `product.is_available_online=true && product.is_active=true` — checked in `InventoryObserver.updated()` only
- **New entities:** Define eligibility rules in the Observer before firing the event

### Lock Mechanism

- Acquire: `$syncQueue->acquireLock(getmypid())`
- Release: always in `finally` block
- If acquire fails: log and return silently (another worker is handling it)

---

## Don'ts

- **Don't** use `DB::` — use Eloquent with explicit connections
- **Don't** dispatch jobs inside DB transactions without `public bool $afterCommit = true;` on the listener
- **Don't** use `default` queue for any sync job
- **Don't** skip the idempotency check before inserting queue records
- **Don't** create a new SyncController — add methods to the existing `SyncController.php`
- **Don't** add central sync routes outside the existing sync group in `routes/central.php`
- **Don't** call `tenant()` in central-side jobs — use `tenant_id` from the payload
- **Don't** validate eligibility in the DTO factory — that belongs in the Observer
- **Don't** validate payload structure in the Observer — that belongs in the Form Request
- **Don't** swallow exceptions silently — always log and set `error_code`
- **Don't** skip the lock mechanism — concurrent processing causes duplicates
- **Don't** hard-code the central API URL — always use `config('services.central_api.url')`

---

## Reference Files

| File Type | Pattern to Follow |
|-----------|-------------------|
| DTO | `app/DataTransferObjects/Sync/ProductSyncDTO.php` |
| Event | `app/Events/Tenant/ProductMarketplaceSyncRequested.php` |
| Listener | `app/Listeners/Tenant/EnqueueProductMarketplaceSync.php` |
| Sync Service | `app/Services/Tenant/Sync/ProductSyncService.php` |
| Observer | `app/Observers/Tenant/ProductObserver.php` |
| Outbound Job (tenant) | `app/Jobs/Tenant/ProcessOutboundProductSync.php` |
| Form Request | `app/Http/Requests/Central/Sync/InboundProductSyncRequest.php` |
| SyncController | `app/Http/Controllers/Api/Central/Sync/SyncController.php` |
| Inbound Job (central) | `app/Jobs/Central/ProcessInboundProductSync.php` |
| Central Sync Service | `app/Services/Central/Sync/MarketplaceSyncService.php` |
| OutboundSyncService | `app/Services/Central/Marketplace/OutboundSyncService.php` |
| Outbound Enum | `app/Enums/Central/OutboundSyncAction.php` |
| Central Queue Model | `app/Models/SyncQueueOutbound.php` |
| Tenant Queue Model | `app/Models/Tenant/SyncQueueOutbound.php` |
| Inbound Queue Model | `app/Models/SyncQueueInbound.php` |
| Tenant Inbound Job | `app/Jobs/Tenant/ProcessInboundOrderSync.php` |
| Routes | `routes/central.php` (sync group ~lines 165-180) |

**Implemented syncs (use as additional patterns):**

| Entity | Direction | Action | Queue | Trigger | Key Files |
|--------|-----------|--------|-------|---------|-----------|
| Product | Tenant → Central | create, update, delete, activate, deactivate | `sync-high` | `ProductObserver` | `ProductSyncDTO`, `ProcessOutboundProductSync`, `ProcessInboundProductSync` |
| ProductVariant | Tenant → Central | create, update, delete, activate, deactivate | `sync-high` | `ProductVariantObserver` | `ProductVariantSyncDTO`, `ProcessOutboundVariantSync`, `ProcessInboundVariantSync` |
| ProductBundle | Tenant → Central | create, update, delete, activate, deactivate | `sync-high` | `ProductBundleObserver` | `BundleSyncDTO`, `ProcessOutboundBundleSync`, `ProcessInboundBundleSync` |
| InventoryCount | Tenant → Central | update only | `sync-high` | `InventoryObserver.updated()` — fires when `Inventory` row changes; eligibility: `product.is_available_online && product.is_active`; aggregates across all stores | `InventoryCountSyncDTO`, `ProcessOutboundInventoryCountSync`, `ProcessInboundInventoryCountSync`, `MarketplaceInventoryCountSyncService` |
| MarketplaceOrder (reservation) | Central → Tenant | reserve_inventory | `sync-critical` | `ProcessCheckoutReservation` job | `OutboundSyncService::queueOrderSync()`, `ProcessInboundOrderSync` |
| MarketplaceOrder (payment) | Central → Tenant | payment_confirmed | `sync-critical` | `ProcessPaymentConfirmation` job | `OutboundSyncService::queuePaymentSync()`, `ProcessInboundPaymentSync` |
| MarketplaceOrder (cancellation) | Central → Tenant | cancel | `sync-critical` | `ProcessOrderCancellation` job | `OutboundSyncService::queueCancellationSync()`, `ProcessInboundCancellationSync` |

---

## After Implementation — Update This Skill

Once the new sync is working, update **this file** (`.claude/commands/sync.md`):

- Add the new entity to any relevant examples in this document
- Add its trigger point to the trigger table in Step 3 of the Central → Tenant section if it's new
- Add to the Reference Files table if it becomes a useful pattern
- Update queue mapping if a new tier was used

This keeps the skill accurate for all future syncs.
