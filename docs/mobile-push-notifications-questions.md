# Mobile Push Notifications - Questions For Mobile Team

Use this document to capture the mobile team's answers before wiring the final push provider transport and event-specific triggers.

> **Status: answered by mobile 2026-08-18.** Answers below are from the Flutter
> Merchant POS app (`poachy-business-solution/Poachy-Mobile`). Two items need
> backend input before they can be closed — both are flagged **NEEDS BACKEND**.
> One item, topic targeting, has a **multi-tenancy hazard** — please read it.

## Provider And Tokens

- [x] **Provider choice**: Are we using Firebase Cloud Messaging (FCM) for both Android and iOS, or APNs directly for iOS?
  - Answer: **FCM for both platforms.** The app uses the `firebase_messaging`
    Flutter plugin; FCM relays to APNs for iOS delivery. Backend integrates
    against one provider only (FCM HTTP v1 API) and never talks to APNs directly.
    - Ops prerequisite: an **APNs auth key (.p8)** must be uploaded to each
      Firebase project's iOS app config, otherwise iOS delivery silently fails.
      Mobile will supply the key + Key ID + Team ID.

- [x] **Token source**: What exact token will the app send to backend?
  - Options to confirm: FCM registration token, APNs device token, or another provider token.
  - Answer: **FCM registration token** (`FirebaseMessaging.instance.getToken()`)
    on both iOS and Android. Never the raw APNs token — on iOS the app waits for
    the APNs token to be available and then reads the FCM token, so backend only
    ever sees FCM tokens.

- [x] **Supported platforms**: Which platforms should backend accept now?
  - Current backend accepts: `ios`, `android`, `web`.
  - Answer: **`ios` and `android` only** for now. There is no Flutter web build
    of the POS app. Keep `web` in the enum (harmless, avoids a later migration)
    but no client will register it.

## Token Lifecycle

- [x] **Registration timing**: When will the app call `POST /api/v1/tenant/device-tokens`?
  - Cases to confirm: login, token refresh, app launch, push-permission grant, provider-token rotation.
  - Answer: all five, treated as **idempotent upserts** keyed on the token string:
    1. **After OTP verification succeeds** (first point at which a tenant bearer exists).
    2. **After `POST /tenant/auth/refresh`** succeeds — re-asserts the token against the new session.
    3. **On app launch** when an authenticated session is restored from secure storage.
    4. **On push-permission grant** (iOS prompt accepted, or Android 13+ `POST_NOTIFICATIONS` granted).
    5. **On `FirebaseMessaging.onTokenRefresh`** (provider rotation) — fires at any time, including mid-session.
  - Backend ask: `POST /device-tokens` must be **upsert, not insert** — the same
    token will be re-sent often. Re-registering an existing token should update
    `last_used_at` and return 200/201 rather than a duplicate-key 422.

- [x] **Revocation timing**: When will the app call `DELETE /api/v1/tenant/device-tokens`?
  - Cases to confirm: logout, push-permission disabled, account switch, uninstall callbacks where available.
  - Answer:
    - **Logout** — called *before* the bearer is cleared (the endpoint is authenticated). If the call fails, logout still proceeds; the token is left to be reaped server-side.
    - **Account / workspace switch** — revoke against the outgoing tenant first, then register against the new one after auth.
    - **Push permission revoked** while running — revoke on detection.
    - **Uninstall** — no client callback exists on either platform. Backend must reap on FCM `UNREGISTERED` / `INVALID_ARGUMENT` responses (see below).
  - Backend ask: on an FCM send returning `UNREGISTERED` or `INVALID_ARGUMENT`
    for a token, **delete that token row**. This is the only reliable
    uninstall/stale-token cleanup path.

## Payload Contract

- [x] **Notification tap payloads**: What payload shape does the app expect when the user taps a push notification?
  - Proposed shape:

```json
{
  "type": "marketplace_order_created",
  "order_id": 123,
  "screen": "marketplace_order_detail"
}
```

  - Answer: **Shape accepted, with three additions.** All values must be
    **strings** — FCM's `data` map is `map<string,string>` and non-string values
    are rejected by the HTTP v1 API. The app parses ints back out.

```json
{
  "type": "marketplace_order_created",
  "screen": "marketplace_order_detail",
  "resource_id": "123",
  "tenant_slug": "acme",
  "store_id": "4",
  "occurred_at": "2026-08-18T09:12:33Z"
}
```

  - `resource_id` — single generic id field instead of a per-event key
    (`order_id`, `sale_id`, …). One parser, no per-type branching.
  - `tenant_slug` — **required on every payload.** The app is multi-tenant and a
    device may have been re-pointed at a different workspace since the message
    was queued. The app discards any notification whose `tenant_slug` does not
    match the active session, which prevents cross-tenant data leaks on shared
    devices.
  - `occurred_at` — ISO-8601, lets the app drop stale queued notifications.

- [x] **Event payloads**: Please provide route/action names and required IDs for each event:

  | Event | `type` | `screen` | `resource_id` | Extra keys |
  |---|---|---|---|---|
  | New marketplace order | `marketplace_order_created` | `marketplace_order_detail` | order id | `store_id` |
  | Payment confirmed | `payment_confirmed` | `sale_receipt` | sale id | `store_id`, `amount`, `payment_method` |
  | Low stock | `inventory_low_stock` | `product_detail` | product id | `store_id`, `on_hand`, `reorder_level` |
  | Expiry alert | `inventory_expiry_alert` | `product_batches` | product id | `store_id`, `batch_id`, `expiry_date` |
  | Shift reminder | `shift_reminder` | `shift_detail` | shift assignment id | `store_id`, `starts_at` |

  - Note: `sale_receipt`, `product_detail`, `product_batches` and `shift_detail`
    are **not all built in the app yet**. Unknown `screen` values fall back to the
    home screen rather than crashing, so backend can ship these triggers ahead of
    the mobile screens — no coordination needed on ordering.

- [x] **Foreground behavior**: Should backend send notification payloads, data payloads, or both?
  - Also confirm how the app handles notifications while open.
  - Answer: **Both** — every message carries an FCM `notification` block *and* the
    `data` block above.
    - Rationale: data-only messages are unreliable on iOS (throttled, and dropped
      when the app is force-quit), so a `notification` block is required for the
      OS to display anything when the app is backgrounded or killed.
    - **Foreground:** the app suppresses the OS banner and renders its own
      in-app toast/badge from the `data` block, so it controls placement and
      avoids a banner covering the POS cart mid-sale.
    - **Background/terminated:** the OS displays the `notification` block; tap
      hands `data` to the app for routing.
  - Backend ask: set FCM `apns.headers.apns-priority: "10"` and
    `android.priority: "high"` for the user-visible events above. Silent/sync
    messages (if any are added later) should use priority `5` / `normal` and omit
    the `notification` block.

## Environments And Targeting

- [x] **Environment separation**: Will dev/staging/prod use separate Firebase/APNs projects and credentials?
  - Answer: **Yes — three separate Firebase projects**, matching the app's
    existing flavors (`main.dart` / `main_staging.dart` / `main_prod.dart`):
    `poachy-dev`, `poachy-staging`, `poachy-prod`. Each ships its own
    `google-services.json` / `GoogleService-Info.plist` per flavor.
  - Backend ask: FCM service-account credentials must be **per-environment
    config**, not a committed constant. A prod-credential send to a dev token
    fails with `SENDER_ID_MISMATCH`, which is the expected isolation behavior.

- [x] **Targeting model**: Should backend send only to individual registered staff devices, or also to topics/groups?
  - Topics/groups to consider: `owner`, `manager`, `cashier`, `store:{id}`.
  - Answer: **Individual registered device tokens only. Do not use FCM topics.**
  - ⚠️ **Multi-tenancy hazard — this is the important one.** FCM topics are
    **global to a Firebase project**, not scoped to a tenant. A topic named
    `owner` or `store:4` would be shared by *every merchant* in the environment,
    so a low-stock alert for one merchant's store 4 would be delivered to a
    different merchant's staff. Store ids collide across tenant databases by
    design (each tenant has its own DB with its own auto-increment ids), so
    `store:{id}` is guaranteed to collide.
  - If topics are ever needed for fan-out scale, they **must** be namespaced with
    the tenant, e.g. `t_{tenant_id}_store_{store_id}`. At current staff-per-tenant
    counts, per-token sends are well within FCM limits (500 tokens per batch
    request) and are the safer default.

- [x] **User targeting rules**: For each event, who should receive it?
  - New marketplace order: **owner + manager**, scoped to the order's store. Cashiers excluded — they don't fulfil online orders.
  - Payment confirmed: **the cashier who rang the sale** (sale's user), plus **owner** — this is the M-Pesa STK confirmation, and it currently requires the cashier to poll. Push here removes that polling entirely.
  - Low stock: **owner + manager** of the affected store.
  - Expiry alert: **owner + manager** of the affected store. Daily digest preferred over one-per-batch — a pharmacy will trip dozens at once.
  - Shift reminder: **the assigned staff member only**.
  - Cross-cutting: never notify the user who *caused* the event (no self-notifications), except for `payment_confirmed`, where the cashier is waiting on it.

## Validation

- [ ] **Test tokens**: Can mobile provide one test device token per platform for staging validation?
  - iOS: **NEEDS BACKEND / BLOCKED ON ENV** — a real device is required (the iOS
    Simulator cannot obtain an APNs token, so it cannot produce a usable FCM
    token). Mobile will supply one once the staging Firebase project exists and a
    physical test device is enrolled.
  - Android: can be supplied from an emulator with Play Services as soon as the
    staging Firebase project exists.
  - Web, if applicable: n/a — no web build.
  - **Blocker order:** create the three Firebase projects → share the
    `google-services.json` / `GoogleService-Info.plist` + FCM service-account JSON
    → mobile wires `firebase_messaging` and returns real tokens. Mobile cannot
    produce a token before the project exists.

## Backend Follow-Up Notes

- Current backend progress: tenant device-token storage, authenticated list/register/revoke endpoints, OpenAPI docs, and `SendNotificationJob` push-channel token resolution are implemented.
- Still pending after answers: real FCM/APNs provider transport, provider credentials/config, and explicit event trigger wiring for marketplace orders, payment confirmations, and shift reminders.

### Consolidated backend asks arising from these answers

1. `POST /device-tokens` must be an **idempotent upsert** on the token string, not an insert (avoid duplicate-key 422s on re-registration).
2. **Reap tokens** on FCM `UNREGISTERED` / `INVALID_ARGUMENT` — the only uninstall-detection path.
3. Send **`notification` + `data` together**; all `data` values must be **strings**.
4. Include **`tenant_slug` on every payload** — the app hard-drops mismatches to prevent cross-tenant leakage on shared devices.
5. Use **per-token sends, not topics** (see the multi-tenancy hazard above).
6. FCM credentials as **per-environment config** across three Firebase projects.
7. High priority (`apns-priority: 10` / `android.priority: high`) for the five user-visible events.
8. **Expiry alerts as a daily digest**, not one push per batch.

### Open questions back to backend

- **NEEDS BACKEND:** does `SendNotificationJob` already batch per-token sends, or
  is it one HTTP call per device? At owner+manager fan-out this is small, but the
  expiry digest for a pharmacy could touch many tokens at once.
- **NEEDS BACKEND:** is there an existing per-user notification-preference table?
  If staff can mute a category, the app should surface those toggles in settings
  rather than inventing its own local mute.
