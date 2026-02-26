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
→ SyncQueueInbound (central DB) → Inbound Job → Central Model
→ ACK back to tenant (POST /api/v1/tenant/sync/inbound/{entity}-ack)   ← optional, use when tenant needs confirmation
→ Tenant ACK Controller → SyncQueueOutbound.central_record_id updated
```

**ACK callback rule:** Add the central → tenant ACK when the tenant needs to know the processing result (e.g. it needs `central_record_id` for future operations, or failure must be surfaced). Skip it for stateless syncs (e.g. inventory counts where the tenant never queries central back). See DeliveryZone as the reference pattern for ACK-enabled syncs.

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

## Tenant → Central: 16 Scaffold Steps (follow in order)

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
- Status flow: `pending → processing → validating → syncing → completed` (add `mapping` step only if entity requires `MarketplaceMappingService`)
- Acquire lock; check stale (`markAsCompleted` flow aborts early); deserialize DTO
- Use `MarketplaceMappingService` for category/brand mapping **only when applicable** (e.g. products/variants — not delivery zones)
- Find existing record by `(tenant_id, tenant_{entity}_id)` — update if exists, create if not
- Mark as `stale` if `expires_at` exceeded before processing
- Error codes: VALIDATION_ERROR | MAPPING_ERROR | DUPLICATE_ERROR | SYNC_ERROR | JOB_FAILED
- **`markAsCompleted()` on `SyncQueueInbound` — CRITICAL:** signature is `markAsCompleted(?int $centralRecordId = null, ?string $centralTable = null)`:
  - Job **creates** a new central record → `$syncQueue->markAsCompleted(centralRecordId: $model->id, centralTable: 'tenant_delivery_zones')`
  - Job **updates** an existing record (no new record) → `$syncQueue->markAsCompleted()` — no arguments, never pass an array
- **Note:** `SyncQueueOutbound::markAsCompleted()` has a different signature (`?array $response = null`). Don't conflate the two.
- If ACK is needed (Step 15), fire it in the `finally` block after all processing — always on final success or permanent failure
- **Pattern:** `app/Jobs/Central/ProcessInboundProductSync.php` (with mapping) | `app/Jobs/Central/ProcessInboundDeliveryZoneSync.php` (without mapping, with ACK)

### Step 10 — Central Service `app/Services/Central/Sync/{Prefix}{Entity}SyncService.php`

- **Naming convention:** Use `Marketplace{Entity}SyncService` when the central model is a `Marketplace*` model (e.g. `MarketplaceProduct`). Use `Tenant{Entity}SyncService` when the central model is a `Tenant*` model (e.g. `TenantDeliveryZone`). Never use `Marketplace` prefix for non-marketplace entities.
- `create{Entity}(DTO $dto): Model`
- `update{Entity}(DTO $dto): Model` — if record not found, create it (handles out-of-order delivery)
- `delete{Entity}(DTO $dto): void` — if record not found, log warning + return silently (idempotent)
- **Pattern (marketplace entities):** `app/Services/Central/Sync/MarketplaceSyncService.php`
- **Pattern (tenant entities):** `app/Services/Central/Sync/TenantDeliveryZoneSyncService.php`

### Step 11 — Central Migration (only if new entity type or missing sync columns)

- **New table:** `database/migrations/{timestamp}_create_{entities}_table.php`
  - Must include: `id`, `tenant_id` (FK → tenants), `tenant_{entity}_id`
  - Unique constraint on `(tenant_id, tenant_{entity}_id)`
  - Follow `MarketplaceProduct` column conventions
- **Existing table without sync columns:** Create an ALTER migration instead — `database/migrations/{timestamp}_add_sync_fields_to_{entities}_table.php`
  - Add: `tenant_{entity}_id` (unsignedBigInteger, nullable), `last_synced_at` (timestamp, nullable), `sync_status` (string, nullable)
  - Add unique constraint: `['tenant_id', 'tenant_{entity}_id']`
  - Use nullable columns so existing rows are not broken
  - Set `protected $connection = 'central'` on the migration class
- Run: `php artisan migrate`

### Step 12 — Route `routes/central.php` (inside sync group ~lines 268-290)

```php
Route::post('inbound/{entity}', [SyncController::class, 'receive{Entity}Sync']);
```

### Step 13 — Register Observer

**Do NOT use `AppServiceProvider` or `TenancyServiceProvider`.**
Use the `#[ObservedBy]` PHP 8 attribute directly on the tenant model:

```php
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\Tenant\{Entity}Observer;

#[ObservedBy([{Entity}Observer::class])]
class {Entity} extends Model { ... }
```

### Step 14 — Register Event/Listener

**No manual registration needed.** Laravel 11+ auto-discovers listeners in `app/Listeners/` by scanning the first parameter type hint of public methods. As long as the listener's `handle(YourEvent $event)` signature is type-hinted, it will be registered automatically.

Do NOT add entries to `EventServiceProvider` or `AppServiceProvider` — doing so causes double-registration.

### Step 15 — ACK Controller + Route (only if ACK is needed)

**Skip this step if the sync is stateless (e.g. inventory counts).**
Add when the tenant needs confirmation of central processing (e.g. `central_record_id` for future use, or failure surfacing).

`app/Http/Controllers/Api/Tenant/Sync/TenantSyncAckController.php`:
- Add method `receive{Entity}Ack({Entity}SyncAckRequest $request): JsonResponse`
- Find `App\Models\Tenant\SyncQueueOutbound` by `outbound_sync_queue_id`
- If `status=completed`: update `central_record_id`, `central_table`, merge `sync_response`
- If `status=failed`: call `markAsFailed(reason, 'CENTRAL_PROCESSING_FAILED', [...])`
- Return `200 OK`

`app/Http/Requests/Tenant/Sync/{Entity}SyncAckRequest.php`:
- Rules: `outbound_sync_queue_id` (required|int), `status` (in:completed,failed), `central_{entity}_id` (required_if:status,completed|int), `reason` (nullable|string)

**Pattern:** `app/Http/Controllers/Api/Tenant/Sync/TenantSyncAckController.php` + `app/Http/Requests/Tenant/Sync/DeliveryZoneSyncAckRequest.php`

### Step 16 — ACK Route (only if ACK is needed)

`routes/tenant.php` — inside the existing `sync/inbound` group:

```php
Route::post('/{entity}-ack', [TenantSyncAckController::class, 'receive{Entity}Ack']);
```

Central inbound job must call back to the tenant in its `finally` block:

```php
// In ProcessInbound{Entity}Sync::ackTenant()
$tenant = Tenant::on('central')->find($syncQueue->tenant_id);
$domain = $tenant->domains()->first();
$scheme = app()->environment('local') ? 'http://' : 'https://';
$tenantUrl = $scheme . $domain->domain;

Http::withToken(config('services.tenant_api.token'))
    ->post($tenantUrl . '/api/v1/tenant/sync/inbound/{entity}-ack', [
        'outbound_sync_queue_id' => $tenantOutboundSyncId,  // from $syncQueue->metadata['sync_queue_id_from_tenant']
        'status'                 => $ackStatus,
        'central_{entity}_id'   => $centralEntityId,
        'reason'                 => $reason,
    ]);
```

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
- **DeliveryZone:** No eligibility gate — all zones are always sync-worthy. Observer fires for every `created`, `updated`, and `deleted` event.
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
| Inbound Job (central, with mapping) | `app/Jobs/Central/ProcessInboundProductSync.php` |
| Inbound Job (central, with ACK, no mapping) | `app/Jobs/Central/ProcessInboundDeliveryZoneSync.php` |
| Central Sync Service (Marketplace entities) | `app/Services/Central/Sync/MarketplaceSyncService.php` |
| Central Sync Service (Tenant entities) | `app/Services/Central/Sync/TenantDeliveryZoneSyncService.php` |
| ACK Controller | `app/Http/Controllers/Api/Tenant/Sync/TenantSyncAckController.php` |
| ACK Form Request | `app/Http/Requests/Tenant/Sync/DeliveryZoneSyncAckRequest.php` |
| OutboundSyncService | `app/Services/Central/Marketplace/OutboundSyncService.php` |
| Outbound Enum | `app/Enums/Central/OutboundSyncAction.php` |
| Central Queue Model | `app/Models/SyncQueueOutbound.php` |
| Tenant Queue Model | `app/Models/Tenant/SyncQueueOutbound.php` |
| Inbound Queue Model | `app/Models/SyncQueueInbound.php` |
| Tenant Inbound Job | `app/Jobs/Tenant/ProcessInboundOrderSync.php` |
| Routes (central sync group) | `routes/central.php` (~lines 268-290) |
| Routes (tenant sync group) | `routes/tenant.php` (sync/inbound group) |

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
| DeliveryZone | Tenant → Central | create, update, delete | `sync-high` | `DeliveryZoneObserver` (no eligibility gate) | `DeliveryZoneSyncDTO`, `ProcessOutboundDeliveryZoneSync`, `ProcessInboundDeliveryZoneSync`, `TenantDeliveryZoneSyncService`, `TenantSyncAckController::receiveDeliveryZoneAck` |

---

## After Implementation — Update This Skill

Once the new sync is working, update **this file** (`.claude/commands/sync.md`):

- Add the new entity to any relevant examples in this document
- Add its trigger point to the trigger table in Step 3 of the Central → Tenant section if it's new
- Add to the Reference Files table if it becomes a useful pattern
- Update queue mapping if a new tier was used

This keeps the skill accurate for all future syncs.
