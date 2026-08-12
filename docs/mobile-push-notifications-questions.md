# Mobile Push Notifications - Questions For Mobile Team

Use this document to capture the mobile team's answers before wiring the final push provider transport and event-specific triggers.

## Provider And Tokens

- [ ] **Provider choice**: Are we using Firebase Cloud Messaging (FCM) for both Android and iOS, or APNs directly for iOS?
  - Answer:

- [ ] **Token source**: What exact token will the app send to backend?
  - Options to confirm: FCM registration token, APNs device token, or another provider token.
  - Answer:

- [ ] **Supported platforms**: Which platforms should backend accept now?
  - Current backend accepts: `ios`, `android`, `web`.
  - Answer:

## Token Lifecycle

- [ ] **Registration timing**: When will the app call `POST /api/v1/tenant/device-tokens`?
  - Cases to confirm: login, token refresh, app launch, push-permission grant, provider-token rotation.
  - Answer:

- [ ] **Revocation timing**: When will the app call `DELETE /api/v1/tenant/device-tokens`?
  - Cases to confirm: logout, push-permission disabled, account switch, uninstall callbacks where available.
  - Answer:

## Payload Contract

- [ ] **Notification tap payloads**: What payload shape does the app expect when the user taps a push notification?
  - Proposed shape:

```json
{
  "type": "marketplace_order_created",
  "order_id": 123,
  "screen": "marketplace_order_detail"
}
```

  - Answer:

- [ ] **Event payloads**: Please provide route/action names and required IDs for each event:
  - New marketplace order:
  - Payment confirmed:
  - Low stock:
  - Expiry alert:
  - Shift reminder:

- [ ] **Foreground behavior**: Should backend send notification payloads, data payloads, or both?
  - Also confirm how the app handles notifications while open.
  - Answer:

## Environments And Targeting

- [ ] **Environment separation**: Will dev/staging/prod use separate Firebase/APNs projects and credentials?
  - Answer:

- [ ] **Targeting model**: Should backend send only to individual registered staff devices, or also to topics/groups?
  - Topics/groups to consider: `owner`, `manager`, `cashier`, `store:{id}`.
  - Answer:

- [ ] **User targeting rules**: For each event, who should receive it?
  - New marketplace order:
  - Payment confirmed:
  - Low stock:
  - Expiry alert:
  - Shift reminder:

## Validation

- [ ] **Test tokens**: Can mobile provide one test device token per platform for staging validation?
  - iOS:
  - Android:
  - Web, if applicable:

## Backend Follow-Up Notes

- Current backend progress: tenant device-token storage, authenticated list/register/revoke endpoints, OpenAPI docs, and `SendNotificationJob` push-channel token resolution are implemented.
- Still pending after answers: real FCM/APNs provider transport, provider credentials/config, and explicit event trigger wiring for marketplace orders, payment confirmations, and shift reminders.
