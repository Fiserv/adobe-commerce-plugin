# Recurring Payments — Complete Engineering Reference

**Branch:** `v1-tyson-subscription`  
**Base Branch:** `dev-140`  
**Author:** Tyson Nguyen  
**Date:** April 2026  
**Audience:** Backend/frontend engineers, QA, on-call support, new developers onboarding to this feature

---

## Table of Contents

1. [What Was Built](#1-what-was-built)
2. [Architecture at a Glance](#2-architecture-at-a-glance)
3. [Scheduling Architecture — Dual-Mechanism Deep Dive](#3-scheduling-architecture--dual-mechanism-deep-dive)
4. [Database Schema](#4-database-schema)
5. [New Files Created](#5-new-files-created)
6. [Existing Files Modified](#6-existing-files-modified)
7. [End-to-End Flow — Initial Checkout](#7-end-to-end-flow--initial-checkout)
8. [End-to-End Flow — Renewal Cycle (Cron)](#8-end-to-end-flow--renewal-cycle-cron)
9. [End-to-End Flow — Customer Actions](#9-end-to-end-flow--customer-actions)
10. [Service Layer Reference](#10-service-layer-reference)
11. [Gateway Layer — Request Builders & Response Handlers](#11-gateway-layer--request-builders--response-handlers)
12. [Frontend Implementation](#12-frontend-implementation)
13. [Admin Panel Implementation](#13-admin-panel-implementation)
14. [Class Interaction Diagram](#14-class-interaction-diagram)
15. [Configuration & Wiring (DI, Events, Cron)](#15-configuration--wiring-di-events-cron)
16. [Key Problems Solved & Design Decisions](#16-key-problems-solved--design-decisions)
17. [UI Behaviour Summary](#17-ui-behaviour-summary)
18. [Code Standards & Naming Conventions](#18-code-standards--naming-conventions)
19. [Debugging Guide](#19-debugging-guide)
20. [Integration Checklist](#20-integration-checklist)

---

## 1. What Was Built

A complete, production-ready **recurring / subscription payment system** integrated into Adobe Commerce (Magento), powered by the **CommerceHub (Fiserv)** payment gateway.

Customers can opt into a subscription at checkout. From that point forward, the system automatically charges them on a configurable schedule — every minute (for testing), day, week, month, or year — without any manual intervention from the merchant or the customer.

**Key capabilities:**
- Fully automated renewal via Magento cron (every-minute sweeper)
- Precise scheduling hint injection for minute-interval subscriptions
- Atomic row-claim to prevent double-charge race conditions between parallel cron workers
- Vault token per-customer isolation (no cross-customer token contamination — resolves HTTP 400 / error 695)
- Customer self-service: view history, cancel subscription, void individual transactions, update saved card
- Admin panel: global view of all subscriptions, cancel & card-update on behalf of any customer
- Real-time AJAX table polling (5-second refresh, page-visibility-aware)
- Status history comments written to every Magento order in the chain

---

## 2. Architecture at a Glance

```
Customer Checks Out
        │
        ▼
[Checkout Form]
 ── "Subscribe" checkbox
 ── Interval selector (1 minute / 1 day / 1 week / 1 month / 1 year)
 ── "Save for later use" forced on (vault token required for renewals)
        │
        ▼
[CommerceHub Auth Pipeline]
 ── StoredCredentialsDataBuilder   → storedCredentials { sequence: FIRST, ... }
 ── AdditionalDataCommonDataBuilder → billPaymentType: RECURRING
 ── VaultDetailsHandler            → saves token to vault_payment_token (per-customer hash)
 ── SubscriptionDetailsHandler     → persists schemeReferenceTransactionId
        │
        ▼
[Observer: SaveSubscriptionOrder]  (fires on checkout_submit_all_after)
 • Creates subscription_order row (sequence=FIRST, status=active, is_active=1)
 • Calls CronScheduler::scheduleCronAt() → inserts precise entry into cron_schedule (minute only)
        │
        ▼
[Magento Cron: ProcessSubscriptions]  ← fires at next_billing_datetime
        │
        ▼
[Service: SubscriptionProcessor]
 • Clones original cart via Magento Reorder
 • Deactivates stale open quotes (prevents cart contamination)
 • Resolves vault public_hash (per-customer isolated)
 • Resolves shipping method (reuses parent order's method or falls back)
 • PaymentProcessor → CommerceHub vault_authorize command
 • QuoteManagement::submit() → creates child order (e.g. 000007826-1, -2, -3…)
 • Writes status history: "Authorized amount of $X.XX. Transaction ID: "…""
 • Stamps child row: status=processed, next_billing_datetime=next due time
 • CronScheduler::scheduleCronAt() → schedules next billing cycle
 • Raw SQL guard: restores head row's next_billing_datetime
        │
        ▼
[UI: Customer & Admin phtml + JS]
 • Table of all transactions in the chain
 • Status: Active / Processed / Canceled
 • Payment card used per row
 • Next billing date per row (UTC)
 • Update payment card dropdown (latest active row only)
 • Void individual transaction (child row)
 • End entire subscription (any row)
 • Live AJAX refresh every 5 seconds
```

---

## 3. Scheduling Architecture — Dual-Mechanism Deep Dive

The recurring payment system uses a **dual-mechanism** approach to guarantee reliability across all billing intervals. Understanding both mechanisms — and when each applies — is critical for maintaining this feature.

### 3.1 Mechanism A — Cron Sweeper (`crontab.xml`)

Registered in `etc/crontab.xml`:

```xml
<job name="fiserv_process_subscriptions"
     instance="Fiserv\Payments\Cron\ProcessSubscriptions"
     method="execute">
    <schedule>* * * * *</schedule>
</job>
```

Magento's cron runner fires `ProcessSubscriptions::execute()` **every minute**. Inside, a database query finds subscription rows that are actually due:

```sql
SELECT * FROM subscription_order
 WHERE status  = 'active'
   AND sequence = 'FIRST'
   AND is_active = 1
   AND next_billing_datetime <= NOW()
 ORDER BY next_billing_datetime ASC
```

If nothing matches (most minutes), the method **exits silently** — zero log output.  
If something matches, it claims the row atomically and delegates to `SubscriptionProcessor`.

**This is the mechanism that actually finds and processes due subscriptions.**

### 3.2 Mechanism B — Programmatic `cron_schedule` Insertion (`CronScheduler`)

The `CronScheduler` service inserts a **precise row** directly into Magento's `cron_schedule` table:

```sql
INSERT INTO cron_schedule (job_code, status, created_at, scheduled_at)
VALUES ('fiserv_process_subscriptions', 'pending', '2026-03-13 14:00:00', '2026-03-13 14:01:00')
```

This tells Magento's cron runner: "Execute `fiserv_process_subscriptions` at exactly this minute."

**This is a scheduling hint — it does not process the payment itself.**

### 3.3 Why Both Mechanisms Exist

| Scenario | What handles it | Why |
|---|---|---|
| **Minute-interval** subscriptions | `CronScheduler::scheduleCronAt()` inserts a precise row **+** sweeper executes it | Without the precise entry, Magento might not have generated a `cron_schedule` entry for the target minute yet — the job would be missed entirely. |
| **Day / week / month / year** subscriptions | Sweeper only — `CronScheduler::scheduleCronAt()` returns immediately without inserting | Magento **purges** old `cron_schedule` entries. A precise entry for "2 weeks from now" would be deleted before it fires. The sweeper alone is sufficient for longer intervals. |

### 3.4 Minute-Interval Flow

```
Customer places order (interval = 1 minute)
    │
    ▼
SaveSubscriptionOrder (Observer)
    ├─ Creates subscription_order row
    │   (status=active, next_billing_datetime = now + 1 min → e.g. 14:01:00)
    └─ CronScheduler::scheduleCronAt('14:01:00', 'minute')
        └─ INSERTs into cron_schedule: scheduled_at = 14:01:00
            │
            ▼
Magento Cron Runner fires at ~14:01:03
    │  Sees the cron_schedule entry → runs ProcessSubscriptions::execute()
    ▼
ProcessSubscriptions::execute()
    │  Queries: "Any subscription_order rows where next_billing_datetime <= 14:01:03?"
    │  Finds the row (14:01:00 <= 14:01:03) → YES
    │  Atomic claim → delegates to SubscriptionProcessor
    ▼
SubscriptionProcessor::processSubscription()
    ├─ Charges CommerceHub, creates child order (000007826-1)
    ├─ Computes NEXT billing: now + 1 min → truncate → 14:02:00
    ├─ CronScheduler::scheduleCronAt('14:02:00', 'minute')
    │   └─ INSERTs into cron_schedule: scheduled_at = 14:02:00
    └─ Stamps child row: next_billing_datetime = 14:02:00
            │
            ▼
[cycle repeats]
```

### 3.5 Longer-Interval Flow (Day / Month / etc.)

```
Customer places order (interval = 1 month)
    │
    ▼
SaveSubscriptionOrder
    ├─ Creates row: next_billing_datetime = 2026-04-13 14:00:00
    └─ CronScheduler::scheduleCronAt('2026-04-13 14:00:00', 'month')
        └─ Returns immediately — no cron_schedule entry inserted

[... 30 days pass ...]

Magento Cron Runner fires at ~2026-04-13 14:00:03
    │  ProcessSubscriptions::execute() runs (every minute regardless)
    │  Queries: next_billing_datetime <= 14:00:03? → YES
    │  Atomic claim → SubscriptionProcessor charges and renews
    └─ Next due: 2026-05-13 14:00:00 → CronScheduler no-op → [repeat next month]
```

### 3.6 `CronScheduler` Service Behaviours

| Behaviour | Detail |
|---|---|
| **Minute-only guard** | Returns immediately (no DB write) if `$intervalUnit !== 'minute'` |
| **Past-due fallback** | If `$nextBillingDatetime` is already in the past, schedules 1 minute from `now()` |
| **Truncation to minute boundary** | Floors target time to `:00` seconds; if second component ≥ 30, bumps forward one minute |
| **Duplicate guard** | Checks for existing `pending`/`running` entry at the same `scheduled_at` before inserting |

### 3.7 Component Summary

| Component | Role |
|---|---|
| `crontab.xml` (`* * * * *`) | Registers the job; sweeper runs every minute |
| `ProcessSubscriptions::execute()` | Queries DB for due rows and processes them |
| `CronScheduler::scheduleCronAt()` | Inserts precise `cron_schedule` row for minute-interval subscriptions |
| `next_billing_datetime` column | Source of truth — when a subscription is actually due |
| `getActiveSubscriptionsDueForBilling()` | DB query that finds due rows (`next_billing_datetime <= NOW()`) |

---

## 4. Database Schema

**File:** `etc/db_schema.xml` — New table: **`subscription_order`**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `entity_id` | int (PK, AUTO_INCREMENT) | — | Row identifier |
| `order_increment_id` | varchar(50) UNIQUE | — | Magento order increment ID (e.g. `000007826`, `000007826-1`) |
| `original_order_increment` | varchar(50) | NULL | Chain key — root order ID shared by all rows (e.g. `000007826`) |
| `sequence` | varchar(20) | NULL | `FIRST` (checkout/head row) \| `SUBSEQUENT` (every renewal) |
| `status` | varchar(20) | `active` | `active` \| `processing` (transient mid-cron) \| `processed` \| `cancelled` |
| `is_active` | smallint(1) | `1` | `1` = recurring chain is running; `0` = permanently ended by user |
| `next_billing_datetime` | timestamp | NULL | UTC time when the **next** charge fires — canonical scheduling field |
| `payment_token` | varchar(255) | NULL | Raw TransArmor gateway token (stripped of `$$TS$$=` suffix) |
| `token_source` | varchar(50) | NULL | e.g. `TRANSARMOR` |
| `scheme_reference_transaction_id` | varchar(255) | NULL | Fiserv network ID — required for card-on-file billing compliance on SUBSEQUENT charges |
| `customer_id` | int unsigned | NULL | Magento `customer_entity.entity_id` |
| `customer_email` | varchar(255) | NULL | Customer email (convenience field) |
| `customer_name` | varchar(255) | NULL | Customer full name (convenience field) |
| `expiration_month` | varchar(2) | NULL | Card expiry month `MM` |
| `expiration_year` | varchar(4) | NULL | Card expiry year `YYYY` |
| `last_gateway_transaction_id` | varchar(64) | NULL | Most recent CommerceHub transaction ID |
| `change_payment_card` | varchar(32) | NULL | Masked card label shown after "Update card" (e.g. `************1111`); cleared after next renewal |
| `interval_value` | int unsigned | `1` | Billing frequency number (e.g. `1`, `2`) |
| `interval_unit` | varchar(10) | NULL | `minute` \| `day` \| `week` \| `month` \| `year` |
| `failed_attempts` | int unsigned | `0` | Consecutive failed charge count |
| `created_at` | timestamp | `CURRENT_TIMESTAMP` | Row creation time |
| `updated_at` | timestamp | `CURRENT_TIMESTAMP ON UPDATE` | Auto-updated on every save |

### Chain Model

```
000007826          ← HEAD row  (sequence=FIRST,  is_active=1, status=active)
000007826-1        ← child row (sequence=SUBSEQUENT, status=processed)
000007826-2        ← child row (sequence=SUBSEQUENT, status=processed)
000007826-3        ← child row (sequence=SUBSEQUENT, status=active — latest)
```

All rows share `original_order_increment = '000007826'`.

**`is_active` vs `status`:**
- `is_active = 0` → subscription **PERMANENTLY ENDED** (user-initiated cancel). Never modified by cron.
- `status = 'processing'` → **TRANSIENT** state (cron is actively working on this row). Never surfaced to the UI.
- `status = 'cancelled'` → that individual renewal transaction was voided by the user.
- `status = 'processed'` → that renewal completed successfully.

**Next Billing Date per row:**  
Each row stores the `next_billing_datetime` for its **own successor**:
```
000007826   → stores when 000007826-1 fires
000007826-1 → stores when 000007826-2 fires
000007826-2 → stores when 000007826-3 fires
```

---

## 5. New Files Created

### 5.1 Data Contracts (API Layer)

| File | Purpose |
|---|---|
| `Api/Data/SubscriptionOrder/SubscriptionOrderInterface.php` | PHP interface defining all column constants (`ENTITY_ID`, `STATUS`, `IS_ACTIVE`, `CHANGE_PAYMENT_CARD`, etc.) and typed getter/setter signatures |
| `Api/Data/SubscriptionOrder/SubscriptionOrderSearchResultInterface.php` | Standard Magento search-result interface for paginated repository queries |
| `Api/SubscriptionOrder/SubscriptionOrderRepositoryInterface.php` | Repository contract — declares `save()`, `getById()`, `getList()`, `delete()`, `getByOrderIncrementId()`, `getChainHead()`, `getChainHeadByOriginalIncrement()` |

### 5.2 Model Layer

#### `Model/Subscription/Order.php`
Magento ORM model. Maps PHP objects ↔ `subscription_order` database rows.  
Constants: `STATUS_ACTIVE`, `STATUS_PROCESSED`, `STATUS_CANCELLED`.  
Notable typed helpers:
- `getIsActive(): int` — always returns `(int)`
- `getChangePaymentCard(): ?string` — nullable
- `incrementFailedAttempts()` / `resetFailedAttempts()`

#### `Model/ResourceModel/Subscription/Order.php`
Resource model connecting `Order.php` to the `subscription_order` table.

#### `Model/ResourceModel/Subscription/Order/Collection.php`
Magento collection class.  
Key method: **`getActiveSubscriptionsDueForBilling()`** — returns all rows where `status='active'`, `sequence='FIRST'`, `is_active=1`, `next_billing_datetime <= NOW()`.

#### `Model/SubscriptionOrder/SubscriptionOrderRepository.php`
Data access layer — CRUD + business queries:
- `getByOrderIncrementId($incrementId)` — direct lookup
- `getChainHead($row)` — given any row, resolves to the root FIRST row
- `getChainHeadByOriginalIncrement($rootIncrement)` — finds head using `original_order_increment`; prefers `order_increment_id = root`; falls back to oldest row by `created_at ASC`

### 5.3 Observer

#### `Observer/SaveSubscriptionOrder.php`
Fires on `checkout_submit_all_after`.

**Guards (exits early if any fail):**
```php
if ($payment->getAdditionalInformation('is_recurring_order')) return; // cron-placed child orders
if (!$payment->getAdditionalInformation('is_subscription'))  return; // non-subscription orders
if (!$intervalValue || !$intervalUnit)                       return; // missing interval fields
```

**Happy path:**
1. `normalizeIntervalUnit()` — maps aliases: `'minutes'`→`'minute'`, `'days'`→`'day'`, `'monthly'`→`'month'`, etc.
2. `computeNextBillingDatetime()` — adds interval to `now(UTC)`, truncates to minute boundary:
   ```php
   if ($intervalUnit === 'minute') {
       $nowUtc->modify('+' . ($intervalValue * 60) . ' seconds');
   } else {
       $nowUtc->modify(sprintf('+%d %s', $intervalValue, $intervalUnit));
   }
   $nowUtc->setTime((int)$nowUtc->format('H'), (int)$nowUtc->format('i'), 0);
   ```
3. Creates `subscription_order` row: `sequence=FIRST`, `status=active`, `is_active=1`
4. Calls `CronScheduler::scheduleCronAt($nextBillingDatetime, $intervalUnit)`

**`normalizeIntervalUnit()` match table:**
```
'min'|'mins'|'minute'|'minutes'          → 'minute'
'day'|'days'|'d'                          → 'day'
'week'|'weeks'|'w'|'weekly'              → 'week'
'month'|'months'|'monthly'               → 'month'
'year'|'years'|'y'|'annual'|'yearly'     → 'year'
default                                   → 'minute'
```

### 5.4 Cron Job

#### `Cron/ProcessSubscriptions.php`
Registered as `fiserv_process_subscriptions` with `* * * * *` schedule.

**Flow:**
1. `resetStaleProcessingRows(15)` — resets rows stuck in `'processing'` for > 15 minutes back to `'active'` (handles worker crash/timeout recovery)
2. `getActiveSubscriptionsDueForBilling()` — **exits silently with zero log output** if nothing is due
3. For each due row: **atomic SQL claim** (`UPDATE status='processing' WHERE status='active'`) — if `rowsClaimed !== 1`, another worker won → skip
4. Delegates each claimed row to `SubscriptionProcessor::processSubscription()`
5. Logs completion: `[Processed: N, Success: N, Failed: N]`

### 5.5 Renewal Engine

#### `Service/SubscriptionProcessor.php`
Core service executing one full billing cycle. Constructor (PHP 8.1 promoted readonly):

```php
public function __construct(
    private readonly SubscriptionOrderRepository     $subscriptionRepository,
    private readonly OrderRepositoryInterface        $orderRepository,
    private readonly MagentoReorder                  $magentoReorder,
    private readonly PaymentProcessor                $paymentProcessor,
    private readonly MultiLevelLogger                $logger,
    private readonly SearchCriteriaBuilder           $searchCriteriaBuilder,
    private readonly PaymentTokenManagementInterface $paymentTokenManagement,
    private readonly CartRepositoryInterface         $quoteRepository,
    private readonly ExtensionAttributesFactory      $extensionAttributesFactory,
    private readonly QuoteManagement                 $quoteManagement,
    private readonly ResourceConnection              $resource,
    private readonly CronScheduler                   $cronScheduler
) {}
```

**`processSubscription()` — full step-by-step:**

| Step | Action |
|------|--------|
| 1 | `loadOrderByIncrementId()` — SearchCriteria query on `increment_id` |
| 2 | `MagentoReorder::execute()` — clones cart, preserving items + addresses |
| 3 | `deactivateOtherActiveQuotesForCustomer()` — raw SQL: `UPDATE quote SET is_active=0 WHERE customer_id=? AND is_active=1 AND entity_id<>?` |
| 4 | Resolve shipping: collect rates, reuse parent order's method, fall back to first available |
| 5 | Set payment flags: `is_recurring_order=true`, `is_subscription=true`, `scheme_reference_transaction_id`, `original_order_increment`, `sequence=SUBSEQUENT` |
| 6 | `getNextSuffixForRootFromSalesOrder()` — queries `sales_order LIKE '{ROOT}-%'`, finds `max(suffix) + 1` |
| 7 | `resolveVaultPublicHash()` — 64-char hex string → direct use; otherwise queries `vault_payment_token` scoped to `customer_id` |
| 8 | `tryAttachVaultTokenAndExpiry()` — attaches vault token to extension attributes; parses expiry from vault token details JSON |
| 9 | `PaymentProcessor::processSubscriptionPayment()` → CommerceHub auth |
| 10 | `QuoteManagement::submit()` → creates child Magento order |
| 11 | `addCommentToStatusHistory()`: `"Authorized amount of $X.XX. Transaction ID: "abc123""` |
| 12 | `computeNextBillingDatetime()` — same logic as `SaveSubscriptionOrder` |
| 13 | `CronScheduler::scheduleCronAt()` — injects next `cron_schedule` entry (minute-interval only) |
| 14 | Update child row: `status=processed`, `next_billing_datetime`, interval + customer data |
| 15 | Restore head row + **Raw SQL guard**: `status=active`, `is_active=1`, clear `change_payment_card`, raw SQL UPDATE locks head's `next_billing_datetime` |
| On exception | `cleanupRenewalQuoteSafely()` — deactivates + empties the renewal quote; re-throws for cron to log |

**Private helpers:**

| Method | Purpose |
|---|---|
| `deactivateOtherActiveQuotesForCustomer(int $customerId, int $keepQuoteId)` | Raw SQL — kills stale open carts |
| `cleanupRenewalQuoteSafely(?Quote $quote)` | Deactivates + clears items on any failure path |
| `resolveVaultPublicHash(string $token, ?int $customerId): string` | Resolves public_hash; customer-scoped vault query |
| `getNextSuffixForRootFromSalesOrder(string $root): int` | Max suffix + 1 from `sales_order` |
| `attachMerchantOrderId($payment, string $merchantOrderId)` | Writes to `additional_information` + extension attributes |
| `tryAttachVaultTokenAndExpiry($payment, ?int $customerId, string $hash)` | Attaches vault token; parses expiry via `VaultTokenDetailsParser` |
| `loadOrderByIncrementId(string $id)` | SearchCriteria `increment_id = ?` query |

### 5.6 Cron Scheduler Service

#### `Service/CronScheduler.php`
Extracted from inline `scheduleCronAt()` methods that previously existed in both `SaveSubscriptionOrder` and `SubscriptionProcessor`. Single injectable unit.

```php
public function __construct(
    private readonly ResourceConnection $resource,
    private readonly MultiLevelLogger   $logger
) {}

public function scheduleCronAt(string $nextBillingDatetime, string $intervalUnit = 'minute'): void
```

Full behaviour described in [§3.6](#36-cronscheduler-service-behaviours).

### 5.7 Gateway Execution

#### `Service/PaymentProcessor.php`
Executes the CommerceHub auth API call for a recurring charge.

Constructor:
```php
public function __construct(
    private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
    private readonly PaymentTokenManagementInterface $paymentTokenManagement,
    private readonly CommandPoolInterface            $commandPool,
    private readonly PaymentDataObjectFactory        $paymentDataObjectFactory,
    private readonly MultiLevelLogger                $logger,
    private readonly OrderPaymentContextBuilder      $orderPaymentContextBuilder,
    private readonly ExtensionAttributesFactory      $extensionAttributesFactory,
    private readonly VaultTokenDetailsParser         $tokenDetailsParser
) {}
```

**`processSubscriptionPayment(Quote $quote, SubscriptionOrder $subscription)` flow:**

1. Guards: validates `scheme_reference_transaction_id`, `public_hash`, `is_subscription`, `grand_total > 0`
2. `retrievePaymentToken()` — tries `paymentTokenManagement->getByPublicHash()` first, then `paymentTokenRepository->getByPublicHash()` as fallback
3. Validates token is active and is a `PaymentTokenInterface`
4. Resolves `merchantOrderId` from additional info → extension attributes → subscription's own increment ID
5. Attaches vault token to extension attributes
6. `populateExpiryFromVaultOnly()` → `VaultTokenDetailsParser::extractExpiry()` → sets `EXP_MONTH_KEY`/`EXP_YEAR_KEY`
7. Normalises expiry via `VaultTokenDetailsParser::normalizeMonth()`/`normalizeYear()`
8. `quotePaymentNeedsOrderContext()` — checks if `$payment->getOrder()` exists
9. If needed: `OrderPaymentContextBuilder::createOrderPaymentFromQuote()` — in-memory order + payment
10. Dispatches `commandPool->get('vault_authorize')->execute($commandSubject)`
11. Resolves transaction ID: `$orderPayment->getLastTransId()` → `$payment->getLastTransId()` → `extractTransactionIdFromResult()`
12. Returns `['success' => true, 'transaction_id' => '...', 'expiration_month' => '...', 'expiration_year' => '...']`

**`extractTransactionIdFromResult()` fallback chain:**  
`transaction_id` → `transactionId` → `id` → `orderId` → `clientRequestId` → `gatewayResponse.transactionProcessingDetails.transactionId`

### 5.8 In-Memory Order Builder

#### `Service/OrderPaymentContextBuilder.php`
Builds an in-memory `Sales\Order` + `Order\Payment` from a `Quote`. Required because Magento gateway adapters call `$payment->getOrder()` — in a cron context (no HTTP request), Quote payments have no backing order until `QuoteManagement::submit()` completes. This supplies the adapters without persisting anything.

Private methods: `applyIdentity()`, `applyCurrency()`, `applyAddresses()`, `applyPayment()` — all catch `Throwable` silently so optional fields never block gateway execution.

### 5.9 Vault Token Parser

#### `Service/VaultTokenDetailsParser.php`
Centralised parser for vault token JSON. Replaces duplicated logic that previously existed independently in `PaymentProcessor`, `SubscriptionProcessor`, and `SubscriptionPaymentMethodUpdater`.

| Method | Returns | Purpose |
|---|---|---|
| `decode($raw): ?array` | `array\|null` | Decodes JSON string or passes through existing array |
| `extractExpiry($tokenDetails): array` | `[string\|null, string\|null]` | `[expiryMonth, expiryYear]` — searches `expirationDate` (combined `MM/YYYY`), `expirationMonth`/`Year` across top-level + `card`, `source.card`, `paymentTokens[0]` |
| `extractMaskedCC($tokenDetails): string` | `string` | Returns `maskedCC` value or `''` |
| `extractTokenSource($tokenDetails): ?string` | `string\|null` | Returns `tokenSource`, `token_source`, or `tokenResponseDescription` |
| `normalizeMonth($month): ?string` | `string\|null` | Zero-pads 1–12; `null` for invalid |
| `normalizeYear($year): ?string` | `string\|null` | Returns 4-digit year; expands 2-digit with `'20'` prefix |

`normalizeMonth` and `normalizeYear` are `public` so `PaymentProcessor` can use them directly.

### 5.10 Payment Method Update Service

#### `Service/SubscriptionPaymentMethodUpdater.php`
Handles the "Update card" action from customer and admin UIs.

**`updateChainPaymentToken(int $customerId, string $customerEmail, int $subscriptionId, string $publicHash): string`:**

1. Validate `subscriptionId > 0`, `publicHash !== ''`
2. Load clicked row via `getById()`
3. Ownership check: match `customer_id` first; fall back to email comparison if `customer_id` is null
4. Resolve chain key from `original_order_increment` (or `order_increment_id`)
5. Find chain head via `getChainHeadByOriginalIncrement()`; guard `head.status = 'active'`
6. Validate vault token via `tokenManagement->getByPublicHash($publicHash, $customerId)`
7. `PaymentTokenUtil::getTokenDataFromPersistenceFormat()` — strips `$$TS$$=timestamp` suffix
8. `head->setPaymentToken($rawGatewayToken)`
9. `tryUpdateExpiryFromVault()` → `VaultTokenDetailsParser::extractExpiry()` → updates `expiration_month`/`year`
10. `getMaskedCardFromToken()` → `VaultTokenDetailsParser::extractMaskedCC()`
11. `head->setChangePaymentCard($maskedCard)` — shown in UI as "Updated. Future renewals will use…"
12. `subscriptionRepo->save($head)`
13. Logs: `[Order ID: 000007826] Customer ID: N — new card: ************1111`
14. Returns `$maskedCard` string → controller returns `{ "success": true, "card_label": "..." }`

### 5.11 Shared Data Builder (AJAX)

#### `Service/SubscriptionDataBuilder.php`
Shared service for both frontend and admin AJAX endpoints. Controllers are thin wrappers — all row-building lives here.

**`build(?array $customerFilter, bool $includeCustomerInfo, bool $includeOrderMeta): array`**

**Phase 1 — single pass:** Builds `$incrementIdMap` and `$chainKeyMap`.

**Phase 2 — two small queries:**
- `$firstRows` (sequence=FIRST): builds `headStatusMap`, `headIdMap`, `headIsActiveMap`, `headPendingCardMap`
- `$latestRows` (desc by `created_at`): builds `latestIdMap`

**Phase 3 — sales order join:** Bulk loads matching `sales_order` rows.

**Phase 4 — build rows:**
- `$isChild` = `preg_match('/-\d+$/', $orderIncrementId)`
- `$chainCancelled` = `headIsActiveMap[$chainKey] === 0` — based on **`is_active`**, NOT `status` (see §16)
- `displayStatus` rules:
  - `'cancelled'` → always shown as-is
  - `chainCancelled` → raw status (chain ended)
  - `isLatest` → `'active'`
  - otherwise → `'processed'` (absorbs transient `'processing'` state)
- `displayNext` for parent rows: `computeFirstNextBilling()` = `created_at + interval` (stable, never changes)
- `change_payment_card` propagated from head to all rows; cleared when `chainCancelled = true`

**Admin-only fields** (`includeCustomerInfo = true`):
- `customer_name`, `customer_email`, `customer_id`
- `vault_tokens`: only for `isLatest && displayStatus='active'` rows, via `resolveVaultTokensForCustomer()` (cached per customer_id)

**Frontend-only fields** (`includeOrderMeta = true`): `date`, `amount`, `order_entity_id`

**`resolveVaultTokensForCustomer(int $customerId)`** — raw SQL on `vault_payment_token`:
- Filters: `customer_id`, `payment_method_code = 'fiserv_commercehub'`, `is_active=1`, `is_visible=1`
- Skips tokens where `merchantId` in details JSON doesn't match current merchant
- Returns `[['public_hash' => '...', 'label' => 'Visa ************1111 12/2027'], ...]`

### 5.12 Customer-Facing Controllers

| Controller | Route | Purpose |
|---|---|---|
| `Controller/Subscription/Recurring.php` | `GET /subscription/index/recurring` | Renders subscription history page |
| `Controller/Subscription/RecurringData.php` | `GET /subscription/index/recurringData` | AJAX — returns customer's subscription rows as JSON |
| `Controller/Subscription/Cancel.php` | `POST /subscription/index/cancel` | Cancel chain (end subscription) or void individual transaction |
| `Controller/Subscription/UpdatePayment.php` | `POST /subscription/index/updatePayment` | Update saved card — delegates to `SubscriptionPaymentMethodUpdater` |

### 5.13 Admin Controllers

| Controller | Route | Purpose |
|---|---|---|
| `Controller/Adminhtml/Subscription/Preview.php` | `GET /admin/subscription/preview` | Renders admin subscription management page |
| `Controller/Adminhtml/Subscription/RecurringData.php` | `GET /admin/subscription/recurringData` | AJAX — returns ALL subscription rows (all customers) |
| `Controller/Adminhtml/Subscription/Cancel.php` | `POST /admin/subscription/cancel` | Cancel/void on behalf of any customer |
| `Controller/Adminhtml/Subscription/UpdatePayment.php` | `POST /admin/subscription/updatePayment` | Update card on behalf of any customer (accepts explicit `customer_id` param) |

### 5.14 UI Block Classes

#### `Block/Subscription/Recurring.php`
Powers the **customer-facing** recurring payments table.

| Method | Purpose |
|---|---|
| `getPriorRecurringPayments()` | Loads all subscription rows for the current customer, joins with `sales_order` |
| `getHeadPublicHashForSubscription()` | Returns FIRST row's payment token — identifies the currently-active card |
| `getHeadSubscriptionIdForSubscription()` | Returns root `entity_id` — used as the cancel target |
| `getChainCancelledForSubscription()` | Returns `true` if `is_active = 0` |
| `getLatestSubscriptionIdByChain()` | Returns most recently created row's `entity_id` — marks "Active" row and Update card dropdown |
| `getVaultTokens()` | Reads saved cards from vault, filters by merchant ID, returns `[public_hash, label]` pairs |
| `getScopedSubscriptionCollection()` | Scopes collection to account page (by customer) or order-view page (by order) |

Chain maps (batched, avoids N+1 queries): `headStatusMap`, `headTokenMap`, `headIdMap`, `headSubMap`, `latestIdMap`.

#### `Block/CommerceHub/AdminHtml/Subscription/All.php`
Same as `Recurring.php` but loads ALL subscriptions. Additional: `getVaultTokensForCustomer($customerId)` for admin card-update dropdowns.

### 5.15 UI Templates

| File | Description |
|---|---|
| `view/frontend/templates/subscription/recurring.phtml` | Customer account table — columns: Order, Date, Amount, Interval, Status, Payment method, Next Billing, Cancel |
| `view/adminhtml/templates/subscription/all-recurring-transactions.phtml` | Admin table — same columns + Customer Name, Email, Customer ID |

### 5.16 JavaScript Modules

#### `view/frontend/web/js/subscription/recurring.js`
RequireJS AMD module. Receives a `config` object from the block.

| Function | Purpose |
|---|---|
| `initSearchAndPaging()` | Client-side search + paginator over `<tr class="recurring-row">` elements |
| `fetchDataAndRender()` | GET `dataUrl`, calls `renderFrontendTable(rows)` |
| `renderFrontendTable(rows)` | Re-builds `<tbody>` HTML from AJAX response — status cell, payment editor, cancel button |
| `postCancel(subscriptionId)` | POST to `cancelUrl` with `subscription_id` + `form_key` |
| `postUpdatePayment(subId, publicHash)` | POST to `updateUrl` with `subscription_id`, `order_increment_id`, `public_hash`, `form_key` |
| `togglePaymentEditor(subId, open?)` | Shows/hides the inline "Update card" form |
| `setFeedback(subId, msg, kind)` | Updates `.subscription-update-feedback` (green/red/grey) |
| `populateVaultSelectFromData(selectEl, tokens)` | Fills update-card `<select>` from token array |

**Auto-refresh polling:**
```js
// Polls every 5 seconds, only when tab is visible
setInterval(function () {
    if (!document.hidden) { fetchDataAndRender(); }
}, 5000);
// Guard: window.FISERV_RECURRING_WATCHER_INITIALIZED prevents duplicate intervals
```

**`change_payment_card` propagation flow:**
1. Customer clicks "Save" on the update card editor
2. `postUpdatePayment()` → server returns `{ "success": true, "card_label": "************1111" }`
3. JS shows feedback message immediately
4. Server persisted `change_payment_card` on HEAD row in DB
5. Next 5-second AJAX poll reads it back via `SubscriptionDataBuilder` → renders "Updated…" message for all chain rows
6. After next renewal, `SubscriptionProcessor` clears `change_payment_card` on the head row

#### `view/adminhtml/web/js/view/subscription/admin-recurring.js`
Same capabilities for the admin panel. Talks to admin controller endpoints. Includes `customer_id` in `postUpdatePayment()` payload (admin acts on behalf of any customer).

### 5.17 Checkout UI Changes

#### `view/frontend/web/js/view/payment/method-renderer/commercehub-form.js` *(modified)*
#### `view/frontend/web/template/payment/commercehub/form.html` *(modified)*
Added "Subscribe" checkbox and billing interval dropdown inside the Credit/Debit Card section.  
When "Subscribe" is checked, "Save for later use" is automatically forced on (vault token required for renewals).

#### `view/frontend/web/js/view/payment/method-renderer/commercehub-vault.js` *(modified)*
#### `view/frontend/web/template/payment/commercehub/vault-form.html` *(modified)*
Same subscription UI on the saved-card (vault) checkout form.

---

## 6. Existing Files Modified

| File | What Changed |
|---|---|
| `etc/db_schema.xml` | Added `subscription_order` table |
| `etc/di.xml` | Wired all new service classes, handlers, composites, and builders |
| `etc/events.xml` | Registered `SaveSubscriptionOrder` on `checkout_submit_all_after` |
| `etc/adminhtml/menu.xml` | Added "Recurring Orders" to Fiserv admin menu |
| `Gateway/Request/.../StoredCredentialsDataBuilder.php` | **New** — builds `storedCredentials` block (FIRST / SUBSEQUENT) |
| `Gateway/Request/.../AdditionalDataCommonDataBuilder.php` | **New** — adds `billPaymentType: RECURRING` |
| `Gateway/Request/.../TokenSourceDataBuilder.php` | Expiry pulled from vault token, not user input |
| `Gateway/Request/.../TransactionDetailsDataBuilder.php` | Added `createToken: true`, `merchantOrderId` for recurring |
| `Gateway/Request/.../CancelRefTxnDataBuilder.php` | Updated for void/cancel reference transactions |
| `Gateway/Request/.../ReferenceTransactionDataBuilder.php` | Updated for SUBSEQUENT recurring charges |
| `Gateway/Request/.../Composites (3 files)` | Included `StoredCredentialsDataBuilder` + `AdditionalDataCommonDataBuilder` in pipelines |
| `Gateway/Response/.../VaultDetailsHandler.php` | Per-customer token isolation via `customer_id` in `generatePublicHash()` |
| `Gateway/Response/.../SubscriptionDetailsHandler.php` | **New** — persists `schemeReferenceTransactionId` and sequence after every auth |
| `Gateway/Response/.../CancelHandler.php` | Updated void response handling |
| `Gateway/Response/.../CardDetailsHandler.php` | Updated card detail extraction |
| `Gateway/Response/.../PaymentDetailsHandler.php` | Updated payment detail extraction |
| `Observer/CommerceHub/DataAssignObserver.php` | Added subscription-related constants and payment key handling |
| `view/frontend/layout/checkout_index_index.xml` | Injected subscription UI into checkout |
| `view/frontend/layout/customer_account.xml` | **New** — added recurring payments table to customer account |
| `view/frontend/layout/fiserv_subscription_recurring.xml` | **New** — layout for `/subscription/recurring` |
| `view/frontend/layout/sales_order_view.xml` | Injected recurring table into order view |
| `view/adminhtml/layout/fiserv_subscription_preview.xml` | **New** — layout for admin subscription page |
| `view/base/web/css/subscription.css` | **New** — shared CSS |
| `view/frontend/web/css/recurring.css` | **New** — frontend-specific CSS |

---

## 7. End-to-End Flow — Initial Checkout

### Step 1: Customer submits checkout with subscription

The checkout form collects `is_subscription`, `subscription_interval_value`, `subscription_interval_unit` from the "Subscribe" checkbox and interval dropdown. `DataAssignObserver` fires on `payment_method_assign_data_fiserv_commercehub` and stores all keys into `payment->additionalInformation`.

### Step 2: CommerceHub gateway charge + tokenization

The `vault_authorize` gateway command executes. The request pipeline includes:
- **`StoredCredentialsDataBuilder`** → sends `storedCredentials.sequence = FIRST`, `initiator = CARD_HOLDER`, `scheduled = false`
- **`AdditionalDataCommonDataBuilder`** → sends `additionalDataCommon.billPaymentType = RECURRING`

The response pipeline processes:

**`VaultDetailsHandler`:**
- Reads `paymentTokens[0].tokenData`, `source.card.expirationMonth/Year`, `source.card.last4`
- Builds `vault_payment_token` record with `is_visible=true`
- Generates `public_hash` scoped to customer: `encrypt(customer_id + paymentMethodCode + type + tokenDetails)`
- Stores `merchantId` in token details JSON

**`SubscriptionDetailsHandler`:**
- Guards on `additionalDataCommon.billPaymentType = RECURRING` — ignores non-recurring auth responses
- Reads `storedCredentials.schemeReferenceTransactionId` (5 fallback paths)
- Reads `paymentTokens[0].tokenData` and `tokenSource`
- Sets `is_subscription=true`, `sequence`, `original_order_increment` on payment additional info
- Creates or updates `subscription_order` row

### Step 3: `checkout_submit_all_after` → `SaveSubscriptionOrder`

Guards → `normalizeIntervalUnit()` → `computeNextBillingDatetime()` → creates FIRST row → `CronScheduler::scheduleCronAt()`

### Step 4: `CronScheduler::scheduleCronAt()`

For minute-interval only: past-due guard → duplicate guard → INSERT into `cron_schedule`.  
For day/week/month/year: returns immediately.

---

## 8. End-to-End Flow — Renewal Cycle (Cron)

### Phase 1 — Stale reset
```php
$this->resetStaleProcessingRows(15);
// UPDATE subscription_order SET status='active'
// WHERE status='processing' AND updated_at < NOW() - INTERVAL 15 MINUTE
```

### Phase 2 — Query due rows
```sql
SELECT * FROM subscription_order
 WHERE status = 'active' AND sequence = 'FIRST'
   AND is_active = 1 AND next_billing_datetime <= NOW()
```

### Phase 3 — Atomic claim
```php
$rowsClaimed = $dbConnection->update($tableName,
    ['status' => 'processing'],
    ['entity_id = ?' => $id, 'status = ?' => 'active']  // CAS
);
if ($rowsClaimed !== 1) continue; // another worker won — skip
```

### Phase 4 — `SubscriptionProcessor::processSubscription()`

Full 15-step execution detailed in [§5.5](#55-renewal-engine).

**Key sub-flow — `PaymentProcessor::processSubscriptionPayment()`:**
1. Retrieve vault token (`paymentTokenManagement` → `paymentTokenRepository` fallback)
2. Extract gateway token, token source, expiry from vault record via `VaultTokenDetailsParser`
3. Build order payment context via `OrderPaymentContextBuilder` (cron has no HTTP context)
4. Execute `commandPool->get('vault_authorize')` — sends SUBSEQUENT MIT charge to CommerceHub
5. Resolve transaction ID from result

**Post-charge cleanup:**
- Write status history comment to new child Magento order
- Compute `next_billing_datetime = now + interval` (truncated to minute boundary)
- `CronScheduler::scheduleCronAt()` — inject next `cron_schedule` entry
- ORM save child row: `status=processed`
- ORM save head row: `status=active`, `is_active=1`, clear `change_payment_card`
- **Raw SQL guard**: `UPDATE subscription_order SET next_billing_datetime=? WHERE entity_id=?` — locks head's original `next_billing_datetime` after ORM write

---

## 9. End-to-End Flow — Customer Actions

### Cancel Subscription (End entire chain)

Triggered when `subscription_id` in POST matches the HEAD row:

```
1. Validate form key + customer session ownership (customer_id match, email fallback)
2. Set is_active = 0 on ALL rows WHERE original_order_increment = $chainKey
3. Head row: status → 'processed'
4. Already-processed rows: status untouched (real paid orders preserved)
5. Unprocessed/active rows: status → 'cancelled'
6. Clear change_payment_card on head
7. Add "Subscription Canceled. No further recurring payment will occur."
   to every Magento order's status history
8. Log: "Subscription Recurring Payment finished [Order ID: 000007826]"
```

### Void Individual Transaction

Triggered when `subscription_id` matches a SUBSEQUENT/child row:

```
1. Validate ownership
2. CommandManagerPool::get('fiserv_commercehub')->executeByCode('void', $payment)
3. Mark that row: status → 'cancelled'
4. Add "Authorized amount of $X.XX. Transaction ID: "..."" to that order's status history
   (the subscription chain remains active — is_active stays 1)
```

### Update Payment Card

```
1. POST: subscription_id + public_hash (+ order_increment_id fallback)
2. Resolve subscription_id if order_increment_id provided:
   SELECT subscription_id FROM sales_order WHERE increment_id = ?
3. SubscriptionPaymentMethodUpdater::updateChainPaymentToken()
   (see §5.10 for full 14-step flow)
4. Returns: { "success": true, "card_label": "************1111" }
5. Next renewal uses new token; SubscriptionProcessor clears change_payment_card on success
```

---

## 10. Service Layer Reference

| Service | File | Called By |
|---|---|---|
| `CronScheduler` | `Service/CronScheduler.php` | `SaveSubscriptionOrder`, `SubscriptionProcessor` |
| `SubscriptionProcessor` | `Service/SubscriptionProcessor.php` | `ProcessSubscriptions` (cron) |
| `PaymentProcessor` | `Service/PaymentProcessor.php` | `SubscriptionProcessor` |
| `VaultTokenDetailsParser` | `Service/VaultTokenDetailsParser.php` | `PaymentProcessor`, `SubscriptionProcessor`, `SubscriptionPaymentMethodUpdater` |
| `SubscriptionPaymentMethodUpdater` | `Service/SubscriptionPaymentMethodUpdater.php` | `Controller/Subscription/UpdatePayment`, `Controller/Adminhtml/Subscription/UpdatePayment` |
| `SubscriptionDataBuilder` | `Service/SubscriptionDataBuilder.php` | `Controller/Subscription/RecurringData`, `Controller/Adminhtml/Subscription/RecurringData` |
| `OrderPaymentContextBuilder` | `Service/OrderPaymentContextBuilder.php` | `PaymentProcessor` |

---

## 11. Gateway Layer — Request Builders & Response Handlers

### Request Builders

#### `StoredCredentialsDataBuilder`
Only activates when `is_subscription = true`.

| Order type | `storedCredentials` sent |
|---|---|
| First purchase (`ROOT` increment) | `{ "initiator": "CARD_HOLDER", "scheduled": false, "sequence": "FIRST" }` |
| Renewal (`ROOT-N` increment) | `{ "initiator": "MERCHANT", "scheduled": true, "sequence": "SUBSEQUENT", "schemeReferenceTransactionId": "..." }` |

Detection: `$isRenewal = (bool)preg_match('/-\d+$/', $orderIncrementId)`

#### `AdditionalDataCommonDataBuilder`
Adds `"billPaymentType": "RECURRING"` only when `is_subscription = true` on the payment.

#### `TokenSourceDataBuilder` *(modified)*
Pulls `expiration_month`/`year` from the vault token record — not from user input at checkout.

### Response Handlers

#### `VaultDetailsHandler` *(modified)*
Creates the `vault_payment_token` record. **Token details JSON structure:**
```json
{
  "type": "VI",
  "maskedCC": "************1111",
  "expirationDate": "12/2028",
  "tokenSource": "TRANSARMOR",
  "tokenResponseCode": "00",
  "tokenResponseDescription": "SUCCESS",
  "nameOnCard": "John Doe",
  "merchantId": "merchant-abc-123"
}
```

**`generatePublicHash()`:**
```php
$hashKey = $paymentToken->getCustomerId();  // ← scoped to customer (critical)
$hashKey .= $paymentToken->getPaymentMethodCode()
    . $paymentToken->getType()
    . $paymentToken->getTokenDetails();
return $this->encryptor->getHash($hashKey);
```

#### `SubscriptionDetailsHandler` *(new)*
Fires after every gateway response in the `vault_authorize` chain.

- **Guard:** Only processes when `additionalDataCommon.billPaymentType = RECURRING`
- **Non-recurring responses:** Persists `payment_token` and `scheme_reference_transaction_id` to payment additional info only — does NOT create a subscription row
- **Recurring responses:** Multi-path fallback for `schemeReferenceTransactionId` (5 paths), multi-path for `expirationMonth/Year` (4 candidates), creates/updates `subscription_order` row

---

## 12. Frontend Implementation

### URL Routes

| Route | Type | Controller |
|-------|------|-----------|
| `/subscription/index/recurring` | GET — page render | `Controller/Subscription/Recurring.php` |
| `/subscription/index/recurringData` | GET — AJAX JSON | `Controller/Subscription/RecurringData.php` |
| `/subscription/index/cancel` | POST | `Controller/Subscription/Cancel.php` |
| `/subscription/index/updatePayment` | POST | `Controller/Subscription/UpdatePayment.php` |

### Block & Template

**Block:** `Block/Subscription/Recurring.php`  
**Template:** `view/frontend/templates/subscription/recurring.phtml`

Block injects into JS config:
- `cancelUrl`, `updateUrl`, `dataUrl`
- `initialVaultTokens` — server-rendered vault tokens array (avoids AJAX round-trip for vault dropdown)

### JavaScript: `recurring.js`

**Status cell rendering rules (JS matches PHP):**

```
status = 'cancelled'                          → red "Canceled" label
status = 'active' + is_latest + !chain_cancelled → <select> void dropdown
everything else (incl. 'processing')          → green "Processed" label
chain_cancelled = true                        → gray "Subscription canceled" (overrides all)
```

**Table auto-refresh (5-second polling):**
```js
if (dataUrl && !window.FISERV_RECURRING_WATCHER_INITIALIZED) {
    window.FISERV_RECURRING_WATCHER_INITIALIZED = true;
    setInterval(function () {
        if (!document.hidden) { fetchDataAndRender(); }
    }, 5000);
}
```

---

## 13. Admin Panel Implementation

### URL Routes

| Route | Type | Controller |
|-------|------|-----------|
| `/admin/subscription/preview` | GET — page render | `Controller/Adminhtml/Subscription/Preview.php` |
| `/admin/subscription/recurringData` | GET — AJAX JSON | `Controller/Adminhtml/Subscription/RecurringData.php` |
| `/admin/subscription/cancel` | POST | `Controller/Adminhtml/Subscription/Cancel.php` |
| `/admin/subscription/updatePayment` | POST | `Controller/Adminhtml/Subscription/UpdatePayment.php` |

**Template:** `view/adminhtml/templates/subscription/all-recurring-transactions.phtml`  
**JS:** `view/adminhtml/web/js/view/subscription/admin-recurring.js`

**Differences from frontend:**
- No customer scoping — loads ALL subscriptions across all customers
- Extra columns: Customer Name, Email, Customer ID
- Admin `UpdatePayment` POST includes explicit `customer_id` param
- Vault token dropdown populated per-row for the latest active row per customer
- Uses admin ACL (`Magento_Backend::admin`), no customer session check

---

## 14. Class Interaction Diagram

```
Checkout Form (form.html / vault-form.html)
    │  is_subscription, interval_value, interval_unit
    ▼
DataAssignObserver ──────────────────────────► payment.additional_information
    │
    ▼
[CommerceHub Auth Pipeline — vault_authorize command]
StoredCredentialsDataBuilder ───► storedCredentials block (FIRST/SUBSEQUENT)
AdditionalDataCommonDataBuilder ─► billPaymentType: RECURRING
TokenSourceDataBuilder ──────────► source block (vault token + expiry from vault)
TransactionDetailsDataBuilder ───► createToken: true, merchantOrderId
    │
    ▼
VaultDetailsHandler ─────────────────────── vault_payment_token table
    (public_hash = encrypt(customer_id + methodCode + type + tokenDetails))
SubscriptionDetailsHandler ─────────────── subscription_order row
    (schemeReferenceTransactionId, sequence, tokenData, expiry)
    │
    ▼
SaveSubscriptionOrder (Observer: checkout_submit_all_after)
    │  guards: is_recurring_order=false, is_subscription=true, interval fields present
    │  normalizeIntervalUnit() + computeNextBillingDatetime() + creates FIRST row
    │  CronScheduler::scheduleCronAt() ← injected service
    ▼
cron_schedule table (minute intervals only)
    │  fires at next_billing_datetime
    ▼
ProcessSubscriptions (Cron: * * * * *)
    │  resetStaleProcessingRows(15)
    │  getActiveSubscriptionsDueForBilling() — exits silently if nothing due
    │  atomic SQL claim: UPDATE status='processing' WHERE status='active'
    ▼
SubscriptionProcessor (Service)
    │  loadOrderByIncrementId() → SearchCriteria
    │  MagentoReorder::execute() → clones cart
    │  deactivateOtherActiveQuotesForCustomer() → raw SQL
    │  getNextSuffixForRootFromSalesOrder() → child increment
    │  resolveVaultPublicHash() → vault table lookup (customer_id scoped)
    │  tryAttachVaultTokenAndExpiry() → VaultTokenDetailsParser
    ▼
PaymentProcessor (Service)
    │  retrievePaymentToken() → management + repository fallback
    │  populateExpiryFromVaultOnly() → VaultTokenDetailsParser
    │  quotePaymentNeedsOrderContext() check
    │  OrderPaymentContextBuilder → in-memory order+payment (cron context)
    │  commandPool → vault_authorize → CommerceHub API
    │  extractTransactionIdFromResult() → fallback chain
    ▼
QuoteManagement::submit() → new child Magento order (000007826-N)
addCommentToStatusHistory: "Authorized amount of $X.XX. Transaction ID: "...""
CronScheduler::scheduleCronAt() → schedules next billing entry
Update child row: status=processed, next_billing_datetime, customer data
Restore head row: status=active, is_active=1, clear change_payment_card
Raw SQL guard: locks head's original next_billing_datetime
    │
    ▼
subscription_order row (SUBSEQUENT, processed, next_billing_datetime stamped)

─────────────────────────────────────────────────────────────────
Background AJAX (5-second polling)
Controller/Subscription/RecurringData       ──► SubscriptionDataBuilder
Controller/Adminhtml/Subscription/RecurringData ► SubscriptionDataBuilder
    ├─ chainCancelled = headIsActiveMap[key] === 0  (NOT from status column)
    ├─ displayStatus: 'active' (latest) | 'processed' | 'cancelled'
    ├─ computeFirstNextBilling() for parent rows (stable: created_at + interval)
    └─ resolveVaultTokensForCustomer() → merchant-filtered vault tokens
recurring.js / admin-recurring.js → live table refresh

─────────────────────────────────────────────────────────────────
Cancel button → Controller/Subscription/Cancel
    HEAD row clicked: is_active=0 on all chain rows; head→processed; others→cancelled
    CHILD row clicked: void command → CommerceHub; that row→cancelled

Update card → Controller/Subscription/UpdatePayment
    ▼
SubscriptionPaymentMethodUpdater::updateChainPaymentToken()
    │  PaymentTokenUtil::getTokenDataFromPersistenceFormat() — strip $$TS$$=
    │  VaultTokenDetailsParser::extractExpiry() + extractMaskedCC()
    ▼
head row: payment_token updated, expiry updated, change_payment_card set
```

---

## 15. Configuration & Wiring (DI, Events, Cron)

### `etc/crontab.xml`

```xml
<group id="default">
    <job name="fiserv_process_subscriptions"
         instance="Fiserv\Payments\Cron\ProcessSubscriptions"
         method="execute">
        <schedule>* * * * *</schedule>
    </job>
</group>
```

Runs every minute. Workers are parallel-safe via the atomic claim in `ProcessSubscriptions`.

### `etc/events.xml`

```xml
<!-- Fires after checkout order is placed — creates the subscription row -->
<event name="checkout_submit_all_after">
    <observer name="fiserv_subscription_save"
              instance="Fiserv\Payments\Observer\SaveSubscriptionOrder" />
</event>

<!-- Fires when payment form data is submitted — stores subscription fields -->
<event name="payment_method_assign_data_fiserv_commercehub">
    <observer name="fiserv_commercehub_data_assign"
              instance="Fiserv\Payments\Observer\CommerceHub\DataAssignObserver" />
</event>

<!-- Same data handling for Apple Pay checkout -->
<event name="payment_method_assign_data_fiserv_applepay">
    <observer name="fiserv_applepay_data_assign"
              instance="Fiserv\Payments\Observer\CommerceHub\DataAssignObserver" />
</event>
```

### `etc/di.xml` (key bindings)

- `SubscriptionOrderRepositoryInterface` → `Model/SubscriptionOrder/SubscriptionOrderRepository`
- `vault_authorize` command request builder chain → includes `StoredCredentialsDataBuilder`, `AdditionalDataCommonDataBuilder`
- `vault_authorize` command response handler chain → includes `SubscriptionDetailsHandler`, `VaultDetailsHandler`
- `ProcessSubscriptions`, `SubscriptionProcessor`, `PaymentProcessor`, `CronScheduler`, `SubscriptionDataBuilder`, `SubscriptionPaymentMethodUpdater`, `OrderPaymentContextBuilder`, `VaultTokenDetailsParser` — all wired via constructor injection

---

## 16. Key Problems Solved & Design Decisions

| Problem | Root Cause | Solution |
|---|---|---|
| **Cron log flooding every minute** | Always-on polling loop with no guard | `ProcessSubscriptions` exits silently with zero output when nothing is due |
| **Token cross-contamination (HTTP 400, error 695)** | `public_hash` generated from `gateway_token` — all customers sharing a test card got the same hash | `VaultDetailsHandler::generatePublicHash()` now uses `customer_id` as the primary hash key component |
| **Double-charge race condition** | Two cron workers could claim the same `active` row simultaneously | Atomic SQL CAS `UPDATE status='processing' WHERE status='active'` — only one worker wins (`rowsClaimed=1`) |
| **Cancel button corrupted all child status messages** | Cancel logic overwrote every row to `cancelled` | Cancel now only touches rows not already `processed` or `cancelled` |
| **Non-recurring orders becoming subscriptions** | Observer checked only payment method, not subscription intent | Observer requires `is_subscription=true` **and** interval fields — all three guards enforced |
| **Wrong "Next Billing" times in UI** | Head row's `next_billing_datetime` overwritten by ORM on each renewal | Each row stores its own next-billing time; raw SQL guard restores head row's value after ORM save |
| **`chainCancelled` flashing during active cron** | `status` briefly becomes `'processing'` mid-cycle; logic read `status !== 'active'` | `chainCancelled = headIsActiveMap[key] === 0` — reads `is_active` (never changes during cron) |
| **No live subscription table updates** | No real-time mechanism | 5-second AJAX polling in JS: `setInterval(fetchDataAndRender, 5000)` with `document.hidden` guard |
| **`pending_card_label` / `change_payment_card` not cleared** | No mechanism to clean it up | `SubscriptionProcessor` clears `change_payment_card` on head after each successful renewal |
| **Vault token expiry sourced from user input** | Users could submit stale expiry at checkout | All expiry now extracted from `vault_payment_token.details` JSON via `VaultTokenDetailsParser` |
| **Expiry / maskedCC parsing duplicated across 3 files** | Copy-paste with subtle differences | `VaultTokenDetailsParser` service centralises all vault token detail parsing |
| **`scheduleCronAt()` duplicated in observer and renewal engine** | Copy-paste code | Extracted into `CronScheduler` service; both callers inject it |
| **Cron context missing order-backed payment** | `$payment->getOrder()` returns null in cron (no HTTP request) | `OrderPaymentContextBuilder` constructs an in-memory `Sales\Order` + `Order\Payment` from the Quote |
| **`is_active` column missing initially** | Schema evolved during development | Added to `db_schema.xml`; `bin/magento setup:upgrade` required |

---

## 17. UI Behaviour Summary

### Status Column Rules

| Scenario | Status shown |
|---|---|
| Latest row in an active chain | `Active` (with void dropdown) |
| Successfully charged row | `Processed` (green) |
| Voided/cancelled individual transaction | `Canceled` (red) |
| Head (parent) order once children exist | `Processed` |
| Entire subscription ended (`is_active = 0`) | Existing statuses preserved; Cancel column shows "Subscription canceled" |
| Row status is `'processing'` (mid-cron transient) | Shown as `Processed` — transient state never reaches the UI |

### Customer Account Table Columns

| Column | Value |
|---|---|
| Order | Clickable link → `sales/order/view` |
| Date | Order placement timestamp |
| Amount | Formatted grand total |
| Interval | e.g. "1 minute", "1 month" |
| Status | Active / Processed / Canceled |
| Payment method | `Card used: ************XXXX` + Update card inline editor (latest active row only) |
| Next Billing | UTC timestamp for the next charge |
| Cancel | "Subscription canceled" (red) when `is_active=0`; "Cancel subscription" button otherwise |

### Admin Table
Same columns plus Customer Name, Email, Customer ID. Update card dropdown shows all vault cards for that customer.

### Subscription Checkbox at Checkout

- Checking "Subscribe" forces "Save for later use" on (vault token required)
- "Save for later use" cannot be unchecked while "Subscribe" is checked
- Interval options: `1 minute`, `1 day`, `1 week`, `1 month`, `1 year`

---

## 18. Code Standards & Naming Conventions

A cleanup pass was applied across all subscription PHP files. These rules must be followed for future changes.

### Variable Naming

| Concept | Preferred | Avoid |
|---|---|---|
| Loop variable over subscription rows | `$subscription` | `$sub`, `$row` |
| Order increment ID string | `$orderIncrementId` | `$inc`, `$canonical` |
| Subscription entity ID | `$subscriptionId` | `$subId` |
| Root subscription entity ID | `$rootSubscriptionId` | `$rootSubId` |
| Interval value | `$intervalValue` | `$ival`, `$v` |
| Interval unit | `$intervalUnit` | `$iunit`, `$u` |
| Normalized interval value/unit | `$normalizedValue` / `$normalizedUnit` | `$v`, `$u` |
| DB connection object | `$connection` | `$dbConnection` |
| Vault token details (decoded) | `$details` | `$tokenDetails`, `$tokenDetailsJson` |
| Additional info from payment | `$additionalInfo` | `$ai` |
| Sales order in loop | `$salesOrder` | `$o` |
| Scheme reference from payment | `$schemeRefFromPayment` | `$ai` |

### Constructor Style

All new and updated classes use **PHP 8.1 constructor property promotion** with `private readonly`:

```php
// ✅ Preferred
public function __construct(
    private readonly FooInterface $foo,
    private readonly BarInterface $bar
) {}

// ❌ Avoid
private FooInterface $foo;
public function __construct(FooInterface $foo) {
    $this->foo = $foo;
}
```

### Service Extraction Pattern

When non-trivial logic is needed in two or more places, it lives in its own `Service/` class:

| Duplicated logic | Extracted into |
|---|---|
| `scheduleCronAt()` in `SaveSubscriptionOrder` and `SubscriptionProcessor` | `Service/CronScheduler` |
| Vault token JSON parsing in `PaymentProcessor`, `SubscriptionProcessor`, `SubscriptionPaymentMethodUpdater` | `Service/VaultTokenDetailsParser` |

### Whitespace Rules

- One blank line between methods; never two
- No trailing blank lines at end of file
- No blank lines between adjacent constant declarations
- One blank line between the last constant and the first method

---

## 19. Debugging Guide

### Subscription row not created after checkout

1. Check `var/log/fiserv_payments.log` for `SaveSubscriptionOrder` output
2. Verify `sales_order_payment.additional_information` contains `is_subscription = true`
3. Check `SubscriptionDetailsHandler` — confirm `billPaymentType = RECURRING` in gateway response
4. Confirm `additionalDataCommon.billPaymentType` field is being sent in the request (`AdditionalDataCommonDataBuilder`)
5. Confirm the checkout form has `is_subscription = true` being passed (check `DataAssignObserver`)

### Subscription not renewing

1. Check `subscription_order` row: `status='active'`, `is_active=1`, `next_billing_datetime <= NOW()`?
2. Check `cron_schedule` for `fiserv_process_subscriptions` — is the job being scheduled and running?
3. `ProcessSubscriptions` logs nothing if zero rows are due — that's expected
4. Check for rows stuck in `status='processing'`: if `updated_at < NOW() - 15min`, they self-reset next run
5. Verify cron daemon is running (`ps aux | grep cron`) and Magento cron (`*/1 * * * * php bin/magento cron:run`)

### Double-charge occurred

1. Check `ProcessSubscriptions` atomic claim code is intact (no modifications to the `WHERE status='active'` guard)
2. Query `cron_schedule` for duplicate entries at the same `scheduled_at` minute
3. Check for duplicate `subscription_order` rows with the same `order_increment_id` (UK constraint should prevent this)

### Error 695 / HTTP 400 on renewals

Cross-customer token contamination (see §16). Verify:
1. `VaultDetailsHandler::generatePublicHash()` uses `$paymentToken->getCustomerId()` as first hash component
2. `vault_payment_token.customer_id` is populated for all active tokens
3. `resolveVaultPublicHash()` in `SubscriptionProcessor` passes `customer_id` to vault table query

### "Subscription canceled" flash during active renewals

`chainCancelled` / `is_active` vs `status` bug (see §16). Verify `SubscriptionDataBuilder` uses:
```php
$chainCancelled = isset($headIsActiveMap[$normalizedChainKey])
    && $headIsActiveMap[$normalizedChainKey] === 0;
```
Not `$headStatusMap[$key] !== 'active'`.

### Card update not persisting between page loads

1. Confirm `SubscriptionPaymentMethodUpdater::updateChainPaymentToken()` calls `$head->setChangePaymentCard($maskedCard)` before saves
2. Confirm `SubscriptionDataBuilder` reads `headPendingCardMap` from HEAD rows (`sequence=FIRST`) only
3. Confirm `chain_cancelled = false` — `change_payment_card` is intentionally blanked for cancelled chains

### Vault dropdown empty in "Update card"

1. Frontend: `initialVaultTokens` should be server-rendered in `Block/Subscription/Recurring.php`
2. Admin: `SubscriptionDataBuilder::resolveVaultTokensForCustomer()` — confirm tokens have `payment_method_code='fiserv_commercehub'`, `is_active=1`, `is_visible=1`
3. Verify `merchantId` in token `details` JSON matches `CommerceHubConfig::getMerchantId()` (merchant-ID filtering may exclude tokens)

### `next_billing_datetime` keeps reverting

Raw SQL head guard (see §16). Verify `$connection->update(...)` executes after `subscriptionRepo->save($childRow)` in `SubscriptionProcessor`. If it runs before, the ORM save overwrites the value.

### AJAX table not refreshing

1. Check `window.FISERV_RECURRING_WATCHER_INITIALIZED` — if already `true`, the interval was set up on a previous page load (correct behaviour, prevents duplicates)
2. Confirm `dataUrl` is set in the JS `config` object (injected from block's `getJsConfig()`)
3. Check browser network tab for the AJAX GET to `recurringData` — look for non-200 responses or JSON parse errors

---

## 20. Integration Checklist

Use when setting up Recurring Payments for a new merchant or verifying an existing installation.

### CommerceHub Gateway Config

- [ ] `additionalDataCommon.billPaymentType = RECURRING` is sent for subscription orders (verify in gateway request logs)
- [ ] `storedCredentials` block included with `sequence`, `initiator`, `scheduled` fields
- [ ] `vault_authorize` command is present and enabled in `di.xml`
- [ ] Tokenization strategy allows token storage (`always` or customer-opted-in)
- [ ] `VaultDetailsHandler` includes `customer_id` in `generatePublicHash()` (not `gateway_token`)

### Database

- [ ] `subscription_order` table exists (`bin/magento setup:upgrade` run)
- [ ] `is_active` column exists (verify with `SHOW COLUMNS FROM subscription_order LIKE 'is_active'`)
- [ ] `change_payment_card` column exists (replaces any `pending_card_label` from earlier drafts)

### Cron

- [ ] `fiserv_process_subscriptions` job appears in `SELECT * FROM cron_schedule WHERE job_code='fiserv_process_subscriptions' LIMIT 5`
- [ ] Magento cron is running: `*/1 * * * * /usr/bin/php /path/to/magento/bin/magento cron:run >> /var/log/magento.cron.log`
- [ ] Test stale reset: manually set a row to `status='processing'`, wait 15+ minutes, confirm it reverts to `active`

### Frontend

- [ ] Layout XML wires `Block/Subscription/Recurring.php` for `/subscription/index/recurring`
- [ ] `view/frontend/web/js/subscription/recurring.js` deployed to `pub/static`
- [ ] Customer saved cards visible in "Update card" dropdown
- [ ] 5-second polling visible in browser Network tab (GET `recurringData` every 5s when tab is visible)

### Admin

- [ ] ACL allows admin user to access `adminhtml/subscription/preview`
- [ ] All four admin AJAX endpoints respond with 200 and valid JSON
- [ ] Admin "Update card" dropdown correctly scoped to the customer's vault tokens

### Test Scenarios

1. **New subscription (minute interval)** — place order; verify `subscription_order` row exists with `sequence=FIRST`, `is_active=1`, `status=active`, `next_billing_datetime ≈ now + 1min`; verify `cron_schedule` entry inserted
2. **First renewal** — wait 1–2 minutes; verify child order `000007826-1` created; new `subscription_order` row `sequence=SUBSEQUENT`, `status=processed`; head `next_billing_datetime` advanced to next interval
3. **Parallel cron safety** — trigger two simultaneous cron runs; verify only one renewal order created (no `subscription_order` duplicates)
4. **Cancel subscription (end chain)** — click "Cancel subscription"; verify ALL chain rows have `is_active=0`; verify no new renewal fires after next cron run
5. **Void transaction** — click "Void transaction" on a child row; verify only that row `status=cancelled`; verify parent `is_active` remains `1` and chain continues
6. **Update card** — select a different saved card; verify HEAD row `payment_token` updated in DB; verify `change_payment_card` label shown in UI; verify next renewal uses the new card and clears `change_payment_card`
7. **Stale processing reset** — manually set `status='processing'`, `updated_at = NOW() - INTERVAL 16 MINUTE`; run cron; verify row reset to `status='active'`
8. **Longer interval (month)** — verify `CronScheduler` does NOT insert a `cron_schedule` entry; verify sweeper picks up renewal when `next_billing_datetime` is reached

