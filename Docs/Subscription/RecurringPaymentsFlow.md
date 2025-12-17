# Recurring Payments Feature — Engineering Reference

**Branch:** `v1-tyson-subscription`  
**Author:** Tyson Nguyen  
**Base Branch:** `qa-140`  
**Date:** March 2026

---

## Table of Contents

1. [What Was Built](#1-what-was-built)
2. [Architecture Overview](#2-architecture-overview)
3. [Scheduling Architecture — How Recurring Payments Execute](#3-scheduling-architecture--how-recurring-payments-execute)
4. [Database Schema](#4-database-schema)
5. [New Files Created](#5-new-files-created)
6. [Existing Files Modified](#6-existing-files-modified)
7. [End-to-End Data Flow](#7-end-to-end-data-flow)
8. [Class Interaction Diagram](#8-class-interaction-diagram)
9. [Key Problems Solved](#9-key-problems-solved)
10. [UI Behaviour Summary](#10-ui-behaviour-summary)
11. [Code Standards & Naming Conventions](#11-code-standards--naming-conventions)

---

## 1. What Was Built

A complete, production-ready **recurring / subscription payment system** integrated into Adobe Commerce (Magento), powered by the **CommerceHub (Fiserv)** payment gateway.

Customers can opt into a subscription at checkout. From that point forward, the system automatically charges them on a configurable schedule — every minute, day, week, month, or year — without any manual intervention from the merchant or the customer.

---

## 2. Architecture Overview

```
Customer Checks Out
        │
        ▼
[Checkout Form]
 ── "Subscribe" checkbox
 ── Interval selector (1 minute / 1 month / etc.)
 ── "Save for later use" (vault token saved)
        │
        ▼
[Observer: SaveSubscriptionOrder]
 • Creates a row in subscription_order (sequence = FIRST, status = active, is_active = 1)
 • Calls scheduleCronAt() → inserts a precise entry into cron_schedule
        │
        ▼
[Magento Cron: ProcessSubscriptions]   ← fires at exact next_billing_datetime
        │
        ▼
[Service: SubscriptionProcessor]
 • Clones the original cart via Magento Reorder
 • Resolves vault token (per-customer isolated)
 • Calls CommerceHub auth via PaymentProcessor
 • Creates child order (e.g. 000007826-1, -2, -3 …)
 • Writes status history comment: "Authorized amount of $X.XX. Transaction ID: "…""
 • Stamps child row: status = processed, next_billing_datetime = next due time
 • Schedules the NEXT cron entry via scheduleCronAt()
 • Keeps head row status = active for as long as the subscription is live
        │
        ▼
[UI: Customer & Admin phtml + JS]
 • Table of all transactions in the chain
 • Status column: Active / Processed / Canceled
 • Card used per row
 • Next billing date per row
 • Update payment card dropdown (latest active row only)
 • Cancel individual transaction (void)
 • End entire subscription
```

---

## 3. Scheduling Architecture — How Recurring Payments Execute

The recurring payment system uses a **dual-mechanism** approach: a Magento cron sweeper **plus** programmatic `cron_schedule` insertion. Understanding why both exist — and when each one is used — is critical to maintaining this feature.

### 3.1 The Two Mechanisms

#### Mechanism A — Cron Sweeper (`crontab.xml`)

Registered in `etc/crontab.xml` as:

```xml
<job name="fiserv_process_subscriptions"
     instance="Fiserv\Payments\Cron\ProcessSubscriptions"
     method="execute">
    <schedule>* * * * *</schedule>
</job>
```

Magento's cron runner fires `ProcessSubscriptions::execute()` **every minute**.
Inside that method, a database query finds subscription rows that are actually due:

```sql
SELECT * FROM subscription_order
 WHERE status = 'active'
   AND sequence = 'FIRST'
   AND is_active = 1
   AND next_billing_datetime <= NOW()
 ORDER BY next_billing_datetime ASC
```

If nothing matches (most minutes), the method **exits silently** — zero log output.
If something matches, it claims the row atomically and delegates to `SubscriptionProcessor`.

**This is the mechanism that actually finds and processes due subscriptions.**

#### Mechanism B — Programmatic `cron_schedule` Insertion (`scheduleCronAt()`)

After every successful charge (or at initial order placement), the code inserts a **precise row** directly into Magento's `cron_schedule` database table:

```sql
INSERT INTO cron_schedule (job_code, status, created_at, scheduled_at)
VALUES ('fiserv_process_subscriptions', 'pending', '2026-03-13 14:00:00', '2026-03-13 14:01:00')
```

This tells Magento's cron runner: "Execute `fiserv_process_subscriptions` at exactly this minute."

**This is a scheduling hint — it does not process the payment itself.**
The inserted entry triggers `ProcessSubscriptions::execute()`, which then runs the same `next_billing_datetime <= NOW()` query to find and process the due row.

### 3.2 Why Both Mechanisms Exist

| Scenario | What handles it | Why |
|---|---|---|
| **Minute-interval** subscriptions (e.g. every 1 min) | `scheduleCronAt()` inserts a precise `cron_schedule` entry **+** sweeper executes it | Without the precise entry, Magento's cron runner might not fire the job during the exact minute needed. Magento generates cron_schedule entries from `crontab.xml`, but only for the near future — if it hasn't generated one for the target minute yet, the job won't run. |
| **Day / week / month / year** subscriptions | Sweeper only (no `cron_schedule` insertion) | Magento **purges** `cron_schedule` entries older than a few days. A precise entry for "2 weeks from now" would be deleted by Magento's cleanup before it ever fires. The sweeper is sufficient because it runs every minute and the `next_billing_datetime <= NOW()` query will catch it once the due date arrives. |

### 3.3 How They Work Together (Minute-Interval Flow)

```
Customer places order (Subscribe = 1 minute)
    │
    ▼
SaveSubscriptionOrder (Observer)
    ├─ Creates subscription_order row
    │   (status=active, next_billing_datetime = now + 1 min, e.g. 14:01:00)
    └─ scheduleCronAt('2026-03-13 14:01:00', 'minute')
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
    ├─ Clones cart, charges CommerceHub, creates child order (000007826-1)
    ├─ Computes NEXT billing datetime: 14:01:03 + 1 min → truncate → 14:02:00
    ├─ scheduleCronAt('2026-03-13 14:02:00', 'minute')
    │   └─ INSERTs into cron_schedule: scheduled_at = 14:02:00
    └─ Stamps child row: next_billing_datetime = 14:02:00
            │
            ▼
Magento Cron Runner fires at ~14:02:03
    │  Sees the cron_schedule entry → runs ProcessSubscriptions again
    ▼
[cycle repeats]
```

### 3.4 How They Work Together (Longer-Interval Flow)

```
Customer places order (Subscribe = 1 month)
    │
    ▼
SaveSubscriptionOrder (Observer)
    ├─ Creates subscription_order row
    │   (status=active, next_billing_datetime = 2026-04-13 14:00:00)
    └─ scheduleCronAt('2026-04-13 14:00:00', 'month')
        └─ Returns immediately — no cron_schedule entry inserted
            (Magento would purge it before April anyway)
            │
            ▼
[... 30 days pass ...]
            │
            ▼
Magento Cron Runner fires at ~2026-04-13 14:00:03
    │  ProcessSubscriptions::execute() runs (it runs every minute regardless)
    │  Queries: "Any subscription_order rows where next_billing_datetime <= 14:00:03?"
    │  Finds the row (14:00:00 <= 14:00:03) → YES
    │  Atomic claim → delegates to SubscriptionProcessor
    ▼
SubscriptionProcessor::processSubscription()
    ├─ Charges, creates child order, etc.
    ├─ Computes NEXT billing: 2026-05-13 14:00:00
    ├─ scheduleCronAt('2026-05-13 14:00:00', 'month') → no-op (skips insert)
    └─ [cycle repeats next month]
```

### 3.5 `scheduleCronAt()` Details

The method exists in two places (identical logic):
- `SaveSubscriptionOrder::scheduleCronAt()` — called at initial order placement
- `SubscriptionProcessor::scheduleCronAt()` — called after each successful renewal

**Key behaviours:**

1. **Minute-only guard**: If `$intervalUnit !== 'minute'`, returns immediately without inserting anything.

2. **Truncation to minute boundary**: The target time is floored to `:00` seconds. If the second component is ≥ 30, it bumps forward one minute (because Magento's cron runner typically fires at ~:03, so a `:30` target in the same minute has already been missed).

3. **Duplicate guard**: Before inserting, checks if a `pending` or `running` entry already exists for the same `scheduled_at` minute. Prevents duplicate entries.

4. **Past-due fallback**: If `next_billing_datetime` is already in the past (e.g. server was down), the entry is scheduled for the current minute so it fires ASAP.

### 3.6 Summary

| Component | Role | Analogy |
|---|---|---|
| `crontab.xml` (`* * * * *`) | Registers the job with Magento's cron runner; sweeper runs every minute | The alarm clock that rings every minute |
| `ProcessSubscriptions::execute()` | The actual payment logic — queries DB for due rows and processes them | The person who wakes up and checks: "Is there anything to do?" |
| `scheduleCronAt()` | Programmatically inserts a precise `cron_schedule` row for minute-interval subscriptions | Setting a specific alarm so you don't oversleep |
| `next_billing_datetime` column | The source of truth — determines when a subscription is actually due | The calendar event that says "pay on this date" |
| `getActiveSubscriptionsDueForBilling()` | The database query that finds due rows (`next_billing_datetime <= NOW()`) | Checking the calendar: "Is anything due right now?" |

**Bottom line**: The sweeper (cron job) is the **executor**. The `scheduleCronAt()` insertion is the **scheduling hint** that guarantees Magento runs the executor at the right minute. For minute-interval subscriptions you need both. For longer intervals the sweeper alone is sufficient.

---

## 4. Database Schema

**File:** `etc/db_schema.xml`

New table: **`subscription_order`**

| Column | Type | Purpose |
|---|---|---|
| `entity_id` | int (PK) | Auto-increment primary key |
| `order_increment_id` | varchar(50) | Links to Magento `sales_order` (e.g. `000007826`) |
| `original_order_increment` | varchar(50) | Always points to the root/parent order — ties the whole chain together |
| `sequence` | varchar(20) | `FIRST` (checkout) or `SUBSEQUENT` (cron renewal) |
| `status` | varchar(20) | `active`, `processed`, `cancelled` |
| `is_active` | smallint | `1` = recurring chain is live, `0` = ended/cancelled |
| `next_billing_datetime` | timestamp | UTC time for when the **next** charge should fire |
| `payment_token` | varchar(255) | TransArmor vault token (unique per customer) |
| `token_source` | varchar(50) | Always `TRANSARMOR` |
| `scheme_reference_transaction_id` | varchar(255) | Fiserv network ID — required for card-on-file billing compliance |
| `customer_id` | int | Magento `customer_entity.entity_id` |
| `customer_email` | varchar(255) | Customer email (convenience field) |
| `customer_name` | varchar(255) | Customer full name (convenience field) |
| `expiration_month` | varchar(2) | Card expiry month for subsequent auth requests |
| `expiration_year` | varchar(4) | Card expiry year for subsequent auth requests |
| `last_gateway_transaction_id` | varchar(64) | Most recent CommerceHub transaction ID |
| `pending_card_label` | varchar(32) | Masked card label shown after an Update Card action (e.g. `************1111`); cleared after next renewal |
| `interval_value` | int | Billing frequency number (e.g. `1`, `2`) |
| `interval_unit` | varchar(10) | Billing frequency unit: `minute`, `day`, `week`, `month`, `year` |
| `failed_attempts` | int | Consecutive failed charge count |
| `created_at` | timestamp | Row creation time |
| `updated_at` | timestamp | Auto-updated on every save |

---

## 5. New Files Created

### 5.1 Data Contract

#### `Api/Data/SubscriptionOrder/SubscriptionOrderInterface.php`
Defines the PHP interface (contract) that every subscription model class must implement.  
Declares all column constants (`ENTITY_ID`, `STATUS`, `IS_ACTIVE`, `PENDING_CARD_LABEL`, etc.) and typed getter/setter signatures.  
This is the canonical reference for all code that reads or writes a subscription row.

#### `Api/Data/SubscriptionOrder/SubscriptionOrderSearchResultInterface.php`
Standard Magento search-result interface for paginated repository queries.

#### `Api/SubscriptionOrder/SubscriptionOrderRepositoryInterface.php`
Repository contract — declares `save()`, `getById()`, `getList()`, `delete()`, `getByOrderIncrementId()`, `getChainHead()`, `getChainHeadByOriginalIncrement()`.

---

### 5.2 Model Layer

#### `Model/Subscription/Order.php`
Magento ORM model. Maps PHP objects ↔ `subscription_order` database rows.  
Implements `SubscriptionOrderInterface`.  
Constants: `STATUS_ACTIVE`, `STATUS_PROCESSED`, `STATUS_CANCELLED`.  
All getters/setters delegate to `getData()` / `setData()`.

#### `Model/ResourceModel/Subscription/Order.php`
Magento resource model. Connects `Order.php` to the `subscription_order` table.

#### `Model/ResourceModel/Subscription/Order/Collection.php`
Magento collection class for multi-row queries.  
Key method: **`getActiveSubscriptionsDueForBilling()`** — returns all rows where:
- `status = 'active'`
- `sequence = 'FIRST'`
- `is_active = 1`
- `next_billing_datetime <= NOW()`

This is the exact query the cron uses to find work.

#### `Model/SubscriptionOrder/SubscriptionOrderRepository.php`
Data access layer — CRUD + business-query methods:
- `save()` / `getById()` / `getList()` / `delete()`
- `getByOrderIncrementId($incrementId)` — direct lookup
- `getChainHead($row)` — given any row in a chain, resolves back to the root `FIRST` row
- `getChainHeadByOriginalIncrement($rootIncrement)` — finds the head using `original_order_increment`, with fallback to oldest row

---

### 5.3 Observer

#### `Observer/SaveSubscriptionOrder.php`
Fires on the `sales_order_place_after` Magento event.

**Guards (skips if any are true):**
1. `is_recurring_order = true` — cron-generated child orders must not create new subscriptions
2. `is_subscription = false` — regular non-subscription orders must be ignored
3. `interval_value` or `interval_unit` is missing — prevents accidental subscription creation

**Happy path:**
1. Reads payment token, scheme reference ID, expiry, interval from order payment `additional_information`
2. Creates a `subscription_order` row: `sequence = FIRST`, `status = active`, `is_active = 1`
3. Calls `scheduleCronAt()` — inserts a precise row into Magento's `cron_schedule` table so `ProcessSubscriptions` fires **exactly** at `next_billing_datetime`

**`scheduleCronAt()` logic:**
- Converts the billing timestamp to the nearest cron-runnable minute
- If the target second is ≥ 30, bumps forward one minute to avoid missing the runner
- Checks for duplicate `cron_schedule` entries before inserting

---

### 5.4 Cron Job

#### `Cron/ProcessSubscriptions.php`
Registered as `fiserv_process_subscriptions` with schedule `* * * * *` in `crontab.xml`.  
The system cron fires Magento's cron runner every minute; this job only produces log output when there is actual work.

**Flow:**
1. Resets any "stuck" `processing` rows older than 15 minutes back to `active` (worker crash recovery)
2. Queries `getActiveSubscriptionsDueForBilling()` — **exits silently with zero log output** if nothing is due
3. For each due row, performs an **atomic SQL claim**: `UPDATE ... SET status = 'processing' WHERE status = 'active'` — prevents double-charging under parallel cron workers
4. Passes each claimed row to `SubscriptionProcessor::processSubscription()`
5. Logs completion summary: `[Processed: N, Success: N, Failed: N]`

---

### 5.5 Renewal Engine

#### `Service/SubscriptionProcessor.php`
Core service that executes one full recurring billing cycle. Called by `ProcessSubscriptions` for each due subscription.

**Flow:**
1. Loads the original parent order
2. Calls Magento's `Reorder::execute()` to clone the cart
3. Deactivates any other active quotes for the same customer (prevents cart contamination)
4. Sets recurring payment flags on the cloned quote payment: `is_recurring_order = true`, `is_subscription = true`, `scheme_reference_transaction_id`, `public_hash`
5. Resolves the next child increment ID (e.g. `000007826-2`) by querying `sales_order` for existing child suffixes
6. Resolves the vault `public_hash` from the stored TransArmor token — **per-customer isolated** to prevent cross-customer token contamination
7. Calls `PaymentProcessor::processSubscriptionPayment()` → CommerceHub auth transaction
8. On success, submits the quote as a new Magento order
9. Calls `addCommentToStatusHistory` on the new order: `Authorized amount of $21.00. Transaction ID: "abc123"`
10. Computes the **next** billing datetime using the subscription's `interval_value` + `interval_unit`
11. Calls `scheduleCronAt()` to schedule the **following** renewal
12. Stamps the child row: `status = processed`, `next_billing_datetime = next due time` (shown in the UI as "Next Billing")
13. Updates the chain head row: keeps `status = active`, `is_active = 1`; clears `pending_card_label` if set
14. Logs: `[Order ID: 000007826-N] Subscription Recurring Payment finished` when the chain ends

---

### 5.6 Gateway Execution

#### `Service/PaymentProcessor.php`
Executes the CommerceHub auth API call for recurring payments.

- Retrieves the vault token from `vault_payment_token` table
- Validates the token is active and belongs to the correct customer
- Populates expiry month/year from vault token details only (not user input)
- Constructs a `PaymentDataObject` and dispatches the `commercehub` payment command
- Returns `['success' => true, 'transaction_id' => '...']` on approval

#### `Service/OrderPaymentContextBuilder.php`
Builds an in-memory `Sales\Order` + `Order\Payment` context from a `Quote`.  
Required because Magento gateway adapters expect an order-backed payment context during cron-triggered auth transactions (no real HTTP request exists at cron time).

---

### 5.7 Payment Method Update Service

#### `Service/SubscriptionPaymentMethodUpdater.php`
Handles the "Update card" action from the UI.

- Validates ownership (customer can only update their own subscription)
- Resolves the chain head row
- Looks up the new vault token by `public_hash`
- Updates `payment_token`, `expiration_month`, `expiration_year` on the **head row only** — historical rows are never modified
- Sets `pending_card_label` on the head row (e.g. `************1111`) — displayed in the UI until the next renewal clears it
- Logs: `[Order ID: 000007826] Payment card updated for subscription. New card: ************1111`

---

### 5.8 Shared Data Builder (AJAX)

#### `Service/SubscriptionDataBuilder.php`
Shared service used by both the frontend and admin AJAX endpoints.  
Builds the full row array from `subscription_order` + `sales_order` data.

- Accepts a `customerFilter` (scope to one customer or all customers)
- Computes chain maps: head status, head token, head subscription ID, latest row ID, is-chain-cancelled flag
- Resolves vault tokens per customer (filtered by merchant ID)
- Returns a standardised `['success' => true, 'rows' => [...]]` payload consumed by the JS modules

---

### 5.9 Gateway Response Handlers

#### `Gateway/Response/CommerceHub/VaultDetailsHandler.php` *(modified)*
Saves the payment token to Magento's `vault_payment_token` table after every successful auth.

**Key changes for recurring payments:**
- Stores `merchantId` in the token details JSON — ensures tokens are isolated per merchant account
- Generates `public_hash` based on `customer_id` (when present) so tokens are **per-customer, not global** — this directly resolved the HTTP 400 / error code 695 "Invalid/Missing Encryption Data" failures

#### `Gateway/Response/CommerceHub/SubscriptionDetailsHandler.php` *(new)*
Runs after every auth response. Reads `storedCredentials.schemeReferenceTransactionId` and `storedCredentials.sequence` from the CommerceHub response and saves them back to the `subscription_order` row.  
Ensures `scheme_reference_transaction_id` is always current after each charge — required for Fiserv network compliance on card-on-file transactions.

---

### 5.10 Gateway Request Builders (new / modified)

#### `Gateway/Request/CommerceHub/StoredCredentialsDataBuilder.php` *(new)*
Builds the `storedCredentials` block in the CommerceHub request payload.  
- First payment: `{ "initiator": "CARD_HOLDER", "scheduled": false, "sequence": "FIRST" }`
- Renewals: `{ "initiator": "MERCHANT", "scheduled": true, "schemeReferenceTransactionId": "...", "sequence": "SUBSEQUENT" }`

#### `Gateway/Request/CommerceHub/AdditionalDataCommonDataBuilder.php` *(new)*
Builds the `additionalDataCommon` block.  
Adds `"billPaymentType": "RECURRING"` only when `is_subscription = true` on the order payment.

#### `Gateway/Request/CommerceHub/TokenSourceDataBuilder.php` *(modified)*
Builds the `source` block for vault-token-based requests.  
Updated to pull `expiration_month` / `expiration_year` from the vault token record rather than from user input.

#### `Gateway/Request/CommerceHub/TransactionDetailsDataBuilder.php` *(modified)*
Sets `createToken: true` and passes `merchantOrderId` for recurring payment transactions.

#### `Gateway/Request/CommerceHub/CancelRefTxnDataBuilder.php` *(modified)*
Builds the reference transaction data for void/cancel requests.

#### `Gateway/Request/CommerceHub/ReferenceTransactionDataBuilder.php` *(modified)*
Builds reference transaction IDs for subsequent recurring charges.

#### `Gateway/Request/CommerceHub/Composite/SessionAuthComposite.php` *(modified)*
#### `Gateway/Request/CommerceHub/Composite/SessionSaleComposite.php` *(modified)*
#### `Gateway/Request/CommerceHub/Composite/TokenAuthComposite.php` *(modified)*
All three composites updated to include `StoredCredentialsDataBuilder` and `AdditionalDataCommonDataBuilder` in the request pipeline.

---

### 5.11 Customer-Facing Controllers

#### `Controller/Subscription/Cancel.php`
Handles the "Cancel subscription" / "Void transaction" button from the customer UI.

**Mode 1 — End subscription (parent row clicked):**
- Sets `is_active = 0` on ALL chain rows
- Leaves `processed` rows as `processed`, leaves `cancelled` rows as `cancelled`
- Rows not yet processed or cancelled are marked `cancelled`
- Clears `pending_card_label` on the head row
- Adds "Subscription Canceled. No further recurring payment will occur." to every order's status history
- Logs: `Subscription Recurring Payment finished`

**Mode 2 — Void individual transaction (child row clicked):**
- Sends a void command to CommerceHub via `CommandManagerPool`
- Marks only that row as `cancelled`
- Adds `Authorized amount of $X.XX. Transaction ID: "..."` to the order's status history
- Security: checks customer session — customers can only cancel their own subscriptions

#### `Controller/Subscription/UpdatePayment.php`
Handles the "Update card" dropdown submit from the customer UI.  
Delegates to `SubscriptionPaymentMethodUpdater`.  
Returns `{ "success": true, "masked_card": "************1111" }` on success.

#### `Controller/Subscription/RecurringData.php`
Frontend AJAX GET endpoint.  
Returns the logged-in customer's subscription rows as JSON.  
Delegates all row-building to `SubscriptionDataBuilder`.

#### `Controller/Subscription/Recurring.php`
Renders the customer-facing recurring payments page (`/subscription/recurring`).

---

### 5.12 Admin Controllers

#### `Controller/Adminhtml/Subscription/Cancel.php`
Same cancel logic as the customer controller but for the admin panel.  
Uses admin ACL (`Magento_Backend::admin`), no customer session check, logs with "Admin" prefix.

#### `Controller/Adminhtml/Subscription/UpdatePayment.php`
Admin version of the UpdatePayment controller. Delegates to `SubscriptionPaymentMethodUpdater`.

#### `Controller/Adminhtml/Subscription/RecurringData.php`
Admin AJAX GET endpoint.  
Returns ALL subscription rows (all customers) as JSON.  
Delegates to `SubscriptionDataBuilder` with `includeCustomerInfo: true`.

#### `Controller/Adminhtml/Subscription/Preview.php`
Renders the admin subscription management page.

---

### 5.13 UI Block Classes

#### `Block/Subscription/Recurring.php`
PHP block class powering the **customer-facing** recurring payments table.  
Fetches and organises all data needed by `recurring.phtml`.

Key methods:

| Method | Purpose |
|---|---|
| `getPriorRecurringPayments()` | Loads all subscription rows for the current customer, joins with `sales_order`, returns combined data array |
| `getHeadPublicHashForSubscription()` | Returns the FIRST row's payment token — identifies the currently-active card |
| `getHeadSubscriptionIdForSubscription()` | Returns the root subscription `entity_id` — used as the cancel target |
| `getChainCancelledForSubscription()` | Returns `true` if `is_active = 0` — drives the "Subscription canceled" display |
| `getLatestSubscriptionIdByChain()` | Returns the most recently created row's `entity_id` — used to mark the "Active" row and show the Update card dropdown |
| `getVaultTokens()` | Reads saved cards from vault, filters by merchant ID, returns `[public_hash, label]` pairs |
| `getScopedSubscriptionCollection()` | Scopes collection to account page (by customer) or order-view page (by order) |

Chain maps (batched to avoid N+1 queries): `headStatusMap`, `headTokenMap`, `headIdMap`, `headSubMap`, `latestIdMap`.

#### `Block/CommerceHub/AdminHtml/Subscription/All.php`
Same as `Recurring.php` but for the admin panel. Loads ALL subscriptions across all customers.

Additional capability:
- `getVaultTokensForCustomer($customerId)` — resolves saved cards for any customer so the admin can update payment methods
- Merchant ID filtering on vault tokens — admin only sees cards valid for the current merchant

---

### 5.14 UI Templates

#### `view/frontend/templates/subscription/recurring.phtml`
HTML table on the customer's account page and on `sales/order/view` pages.

Columns: Order, Date, Amount, Interval, Status, Payment method, Next Billing, Cancel subscription.

#### `view/adminhtml/templates/subscription/all-recurring-transactions.phtml`
Same table for the admin panel.  
Additional columns: Customer Name, Email, Customer ID.

---

### 5.15 JavaScript Modules

#### `view/frontend/web/js/subscription/recurring.js`
Frontend UI interactions:
- **Cancel subscription** — AJAX POST to `Controller/Subscription/Cancel`
- **Void transaction** — same controller, different mode (child row clicked)
- **Update payment card** — AJAX POST to `Controller/Subscription/UpdatePayment`
- **Background table refresh** — periodic AJAX GET to `Controller/Subscription/RecurringData` to refresh the table without a full page reload
- Search/filter, pagination, "Card used" display, `pending_card_label` banner

#### `view/adminhtml/web/js/view/subscription/admin-recurring.js`
Same capabilities for the admin panel.  
Talks to admin controller endpoints.  
Includes vault card dropdown loading via AJAX for the "Update card" functionality.

---

### 5.16 Layout & Config Files

| File | Purpose |
|---|---|
| `etc/crontab.xml` *(new)* | Registers `fiserv_process_subscriptions` with `* * * * *` schedule |
| `etc/db_schema.xml` *(modified)* | Added `subscription_order` table |
| `etc/di.xml` *(modified)* | Wired all new service classes, handlers, and builders |
| `etc/events.xml` *(modified)* | Registered `SaveSubscriptionOrder` observer on `sales_order_place_after` |
| `etc/adminhtml/menu.xml` *(modified)* | Added "Recurring Orders" to Fiserv admin menu |
| `view/frontend/layout/checkout_index_index.xml` *(modified)* | Injected subscription UI into checkout |
| `view/frontend/layout/customer_account.xml` *(new)* | Added recurring payments table to customer account |
| `view/frontend/layout/fiserv_subscription_recurring.xml` *(new)* | Layout for `/subscription/recurring` page |
| `view/frontend/layout/sales_order_view.xml` *(modified)* | Injected recurring table into order view |
| `view/adminhtml/layout/fiserv_subscription_preview.xml` *(new)* | Layout for admin subscription page |
| `view/base/web/css/subscription.css` *(new)* | Shared CSS for subscription tables |
| `view/frontend/web/css/recurring.css` *(new)* | Frontend-specific CSS |

---

### 5.17 Checkout UI

#### `view/frontend/web/js/view/payment/method-renderer/commercehub-form.js` *(modified)*
#### `view/frontend/web/template/payment/commercehub/form.html` *(modified)*
Added "Subscribe" checkbox and billing interval dropdown inside the Credit/Debit Card payment section.  
When "Subscribe" is checked, "Save for later use" is automatically forced on (vault token required for renewals).

#### `view/frontend/web/js/view/payment/method-renderer/commercehub-vault.js` *(modified)*
#### `view/frontend/web/template/payment/commercehub/vault-form.html` *(modified)*
Same subscription checkbox and interval selector on the saved-card (vault) checkout form.

#### `Observer/CommerceHub/DataAssignObserver.php` *(modified)*
Added constants and handling for:
- `IS_SUBSCRIPTION_KEY`
- `SUBSCRIPTION_INTERVAL_VALUE_KEY`
- `SUBSCRIPTION_INTERVAL_UNIT_KEY`
- `PAYMENT_TOKEN_KEY`
- `SCHEME_REFERENCE_TRANSACTION_ID_KEY`
- `EXP_MONTH_KEY`
- `EXP_YEAR_KEY`

These keys carry subscription intent from the checkout form through to the payment gateway pipeline.

---

## 6. Existing Files Modified

| File | What Changed |
|---|---|
| `etc/db_schema.xml` | Added `subscription_order` table |
| `etc/di.xml` | Wired all new classes |
| `etc/events.xml` | Registered `SaveSubscriptionOrder` observer |
| `etc/adminhtml/menu.xml` | Added Recurring Orders menu item |
| `Gateway/Request/.../StoredCredentialsDataBuilder.php` | New builder for stored credentials block |
| `Gateway/Request/.../AdditionalDataCommonDataBuilder.php` | New builder for `billPaymentType: RECURRING` |
| `Gateway/Request/.../TokenSourceDataBuilder.php` | Expiry pulled from vault token, not user input |
| `Gateway/Request/.../TransactionDetailsDataBuilder.php` | Added `createToken`, `merchantOrderId` for recurring |
| `Gateway/Request/.../CancelRefTxnDataBuilder.php` | Updated for void/cancel reference transactions |
| `Gateway/Request/.../ReferenceTransactionDataBuilder.php` | Updated for subsequent recurring charges |
| `Gateway/Request/.../Composites (3 files)` | Included new builders in request pipelines |
| `Gateway/Response/.../VaultDetailsHandler.php` | Per-customer token isolation via `customer_id` in `public_hash` |
| `Gateway/Response/.../CancelHandler.php` | Updated void response handling |
| `Gateway/Response/.../CardDetailsHandler.php` | Updated card detail extraction |
| `Gateway/Response/.../PaymentDetailsHandler.php` | Updated payment detail extraction |
| `Observer/CommerceHub/DataAssignObserver.php` | Added subscription-related payment keys |
| `view/frontend/layout/checkout_index_index.xml` | Injected subscription UI into checkout |
| `view/frontend/layout/sales_order_view.xml` | Injected recurring table into order view |
| `view/frontend/web/js/.../commercehub-form.js` | Subscribe checkbox + interval selector |
| `view/frontend/web/js/.../commercehub-vault.js` | Subscribe checkbox + interval selector (vault) |
| `view/frontend/web/template/.../form.html` | Subscribe UI markup |
| `view/frontend/web/template/.../vault-form.html` | Subscribe UI markup (vault) |
| `view/adminhtml/layout/fiserv_declines_preview.xml` | Minor layout adjustments |

### Post-commit Cleanup (Code Standards Pass)

| File | What Changed |
|---|---|
| `Service/SubscriptionDataBuilder.php` | Renamed `$sub`→`$subscription`, `$inc`→`$orderIncrementId`, `$subId`→`$subscriptionId`, `$ai`→`$additionalInfo`, `$o`→`$salesOrder`; removed trailing blank line |
| `Service/PaymentProcessor.php` | Moved `$resolvedExpiryMonth`/`$resolvedExpiryYear` to point of use |
| `Service/OrderPaymentContextBuilder.php` | Added `declare(strict_types=1)`; promoted constructor properties; renamed `$k`/`$v`→`$key`/`$value`, `$ext`→`$extensionAttributes` |
| `Gateway/Response/CommerceHub/SubscriptionDetailsHandler.php` | Promoted constructor properties; renamed `$ival`→`$intervalValue`, `$iunit`→`$intervalUnit`, `$canonical`→`$orderIncrementId`, `$ai`→`$schemeRefFromPayment`, `$v`/`$u`→`$normalizedValue`/`$normalizedUnit`, `$cand`→`$candidate`; removed extra blank line |
| `Controller/Subscription/Cancel.php` | Promoted constructor properties; removed unused `Context` import and parameter |
| `Controller/Subscription/UpdatePayment.php` | Promoted constructor properties; removed unused `$context` property; renamed `$subId`→`$subscriptionId`, `$orderInc`→`$orderIncrementId`, `$search`→`$searchCriteria`, `$orderSub`→`$subscriptionId` |
| `Controller/Adminhtml/Subscription/UpdatePayment.php` | Renamed `$orderSub`→`$subscriptionId`; removed double blank line |
| `Observer/SaveSubscriptionOrder.php` | Renamed `$normalizedUnit`→`$normalized`, `$scheduledAtFormatted`→`$scheduledAt`, `$dbConnection`→`$connection` |
| `Cron/ProcessSubscriptions.php` | Renamed `$subscriptionResource`→`$resourceModel`, `$subscriptionTable`→`$tableName` |
| `Block/CommerceHub/AdminHtml/Subscription/All.php` | Renamed `$dbConnection`→`$connection`, `$dbSelect`→`$select`, `$vaultTokenTable`→`$vaultTable`, `$tokenDetailsJson`→`$detailsJson`, `$tokenDetails`→`$details`, `$selfSubscriptionId`→`$selfId`; removed redundant try-catch |
| `Model/Subscription/Order.php` | Removed extra blank lines |
| `Model/SubscriptionOrder/SubscriptionOrderRepository.php` | Removed extra blank line |
| `Model/System/Utils/PaymentTokenUtil.php` | Removed empty constructor |

---

## 7. End-to-End Data Flow

### 7.1 New Subscription Created at Checkout

```
1. Customer checks "Subscribe" + selects interval → places order
2. CommerceHub auth fires → VaultDetailsHandler saves token to vault_payment_token
   (public_hash = SHA256 of customer_id + token data → per-customer isolated)
3. SubscriptionDetailsHandler saves schemeReferenceTransactionId back to subscription_order
4. SaveSubscriptionOrder observer fires on sales_order_place_after:
   a. Validates is_subscription = true and interval fields present
   b. Creates subscription_order row: sequence=FIRST, status=active, is_active=1
   c. Calls scheduleCronAt() → inserts precise row into cron_schedule
```

### 7.2 Recurring Charge Fires

```
1. cron_schedule entry fires at next_billing_datetime
2. ProcessSubscriptions::execute():
   a. Resets stale processing rows (recovery)
   b. Queries getActiveSubscriptionsDueForBilling() — finds due rows
   c. Atomic SQL claim: UPDATE status='processing' WHERE status='active'
   d. Passes row to SubscriptionProcessor::processSubscription()
3. SubscriptionProcessor:
   a. Loads parent order → Reorder::execute() → clones cart
   b. Resolves vault public_hash (per-customer)
   c. Calls PaymentProcessor::processSubscriptionPayment()
   d. PaymentProcessor dispatches CommerceHub auth
   e. On approval: submits quote as new child order
   f. Adds "Authorized amount of $X.XX. Transaction ID: "..."" to order history
   g. Computes next billing time → calls scheduleCronAt()
   h. Stamps child row: status=processed, next_billing_datetime=next due
   i. Updates head row: status=active, is_active=1, clears pending_card_label
4. Logs: [Processed: 1, Success: 1, Failed: 0]
```

### 7.3 Customer Ends Subscription

```
1. Customer clicks "Cancel subscription" button
2. Controller/Subscription/Cancel::execute():
   a. Validates form key + customer session ownership
   b. Sets is_active=0 on ALL chain rows
   c. Processed rows stay processed (real paid orders)
   d. Unprocessed future rows → status=cancelled
   e. Clears pending_card_label on head row
   f. Adds "Subscription Canceled. No further recurring payment will occur."
      to every order's status history
   g. Logs: "Subscription Recurring Payment finished [Order ID: 000007826]"
3. JS updates the Cancel column to "Subscription canceled" for all rows
```

### 7.4 Customer Updates Payment Card

```
1. Customer selects new card from dropdown on the latest active row
2. Controller/Subscription/UpdatePayment → SubscriptionPaymentMethodUpdater:
   a. Validates ownership
   b. Looks up new vault token by public_hash
   c. Updates head row: payment_token, expiration_month, expiration_year
   d. Sets pending_card_label = "************XXXX"
   e. Logs: "[Order ID: 000007826] Payment card updated. New card: ************1111"
3. Next renewal uses the new token; SubscriptionProcessor clears pending_card_label
```

---

## 8. Class Interaction Diagram

```
Checkout Form (form.html / vault-form.html)
    │  is_subscription, interval_value, interval_unit
    ▼
DataAssignObserver ──────────────────────► payment.additional_information
    │
    ▼
[CommerceHub Auth Pipeline]
StoredCredentialsDataBuilder ──► storedCredentials block
AdditionalDataCommonDataBuilder ─► billPaymentType: RECURRING
TokenSourceDataBuilder ──────────► source block (vault token)
TransactionDetailsDataBuilder ───► createToken: true
    │
    ▼
VaultDetailsHandler ────────────────────── vault_payment_token table
    (per-customer public_hash via customer_id)
SubscriptionDetailsHandler ─────────────── subscription_order.scheme_reference_transaction_id
    │
    ▼
SaveSubscriptionOrder (Observer)
    │  creates subscription_order row (FIRST, active, is_active=1)
    │  calls scheduleCronAt()
    ▼
cron_schedule table
    │  fires at next_billing_datetime
    ▼
ProcessSubscriptions (Cron)
    │  getActiveSubscriptionsDueForBilling()
    │  atomic SQL claim
    ▼
SubscriptionProcessor (Service)
    │  Reorder → clones cart
    │  PaymentTokenManagement → resolves vault token (per-customer)
    ▼
PaymentProcessor (Service)
    │  OrderPaymentContextBuilder → builds order payment context
    │  CommandPool → CommerceHub auth dispatch
    ▼
[CommerceHub API response → new child order created]
    │  scheduleCronAt() → schedules next billing cycle
    ▼
subscription_order row (SUBSEQUENT, processed, next_billing_datetime stamped)

Background AJAX
    Controller/Subscription/RecurringData ──────► SubscriptionDataBuilder
    Controller/Adminhtml/Subscription/RecurringData ► SubscriptionDataBuilder
    ▼
recurring.js / admin-recurring.js → live table refresh

Cancel button
    Controller/Subscription/Cancel
    Controller/Adminhtml/Subscription/Cancel
    ▼
SubscriptionOrderRepository.save() → is_active=0 on all chain rows

Update card button
    Controller/Subscription/UpdatePayment
    Controller/Adminhtml/Subscription/UpdatePayment
    ▼
SubscriptionPaymentMethodUpdater.updateChainPaymentToken()
    ▼
head row: payment_token + expiry updated, pending_card_label set
```

---

## 9. Key Problems Solved

| Problem | Root Cause | Solution |
|---|---|---|
| Cron sweeper flooding logs every minute with "nothing to do" | Always-on polling loop | `scheduleCronAt()` inserts precise entries; cron exits silently when nothing is due |
| Token cross-contamination (HTTP 400, error 695) | `public_hash` generated without `customer_id` — all customers shared the same token | `VaultDetailsHandler` now includes `customer_id` in `generatePublicHash()` |
| Double-charge race condition | Two cron workers could claim the same row simultaneously | Atomic `UPDATE ... WHERE status = 'active'` claim — only one worker wins |
| Cancel button corrupted all child status messages | Cancel logic set every row to `cancelled` | Cancel now only touches rows not already `processed` or `cancelled` |
| Non-recurring orders becoming subscriptions | `SaveSubscriptionOrder` checked only payment method, not subscription intent | Observer now requires **both** interval fields **and** `is_subscription = true` |
| UI showing wrong "Next Billing" times | Head row's `next_billing_datetime` was overwritten on every renewal | Each row now stores its own `next_billing_datetime` — the time its successor is due |
| No way to tell if a recurring chain is alive | Only `status` column, which changes per-row | New `is_active` column: `1` = chain running, `0` = ended — shared across all chain rows |
| Admin and customer seeing other customers' vault tokens | `public_hash` was global | `merchant_id` stored in vault token details; filtered on display |
| "Update card" label persisting after renewal | No mechanism to clear it | `SubscriptionProcessor` clears `pending_card_label` on the head row after each successful renewal |
| Page required manual refresh to see new transactions | No live update mechanism | Background AJAX polling in JS modules + `RecurringData` JSON endpoints |

---

## 10. UI Behaviour Summary

### Customer Account Recurring Payments Table

| Column | Value |
|---|---|
| Order | Clickable link → `sales/order/view` |
| Date | Order placement timestamp |
| Amount | Formatted grand total |
| Interval | e.g. "1 minute", "1 month" |
| Status | `Active` (latest live row) / `Processed` / `Canceled` |
| Payment method | `Card used: ************XXXX` + Update card dropdown on latest active row only |
| Next Billing | UTC timestamp — when the next charge fires for the next transaction |
| Cancel subscription | "Subscription canceled" (red text) when `is_active = 0`; "Cancel subscription" button otherwise |

### Admin Recurring Payments Table

Same columns plus: **Customer Name**, **Email**, **Customer ID**.  
Update card dropdown shows all vault cards on file for that customer.  
Cancel and void actions available for all rows.

### Status Column Rules

| Scenario | Status shown |
|---|---|
| Latest row in an active chain | `Active` |
| Successfully charged row | `Processed` |
| Voided/cancelled individual transaction | `Canceled` |
| Head (parent) order after child exists | `Processed` |
| Subscription has been ended | Existing statuses preserved; Cancel column shows "Subscription canceled" |

### Subscription Checkbox Behaviour at Checkout

- Checking "Subscribe" automatically forces "Save for later use" on (vault token required for renewals)
- "Save for later use" cannot be unchecked while "Subscribe" is checked
- Interval dropdown: `1 minute`, `1 day`, `1 week`, `1 month`, `1 year`

### Next Billing Date Logic

Each row in the chain stores the `next_billing_datetime` for its **own successor**:

```
Parent order (000007826)   → next_billing_datetime = when 000007826-1 fires
000007826-1                → next_billing_datetime = when 000007826-2 fires
000007826-2                → next_billing_datetime = when 000007826-3 fires
```

This is what the UI displays in the "Next Billing" column per row.

---

## 11. Code Standards & Naming Conventions

A cleanup pass was applied across all subscription-related PHP files to ensure
consistent naming, reduce cognitive load for future developers, and remove dead
code. The rules below should be followed for any future changes to this feature.

### 11.1 Variable Naming Rules

| Pattern | Preferred Name | Avoid |
|---|---|---|
| Loop variable over subscription rows | `$subscription` | `$sub`, `$row` |
| Order increment ID string | `$orderIncrementId` | `$inc`, `$canonical` |
| Subscription entity ID | `$subscriptionId` | `$subId` |
| Root subscription entity ID | `$rootSubscriptionId` | `$rootSubId` |
| Interval value from response | `$intervalValue` | `$ival`, `$v` |
| Interval unit from response | `$intervalUnit` | `$iunit`, `$u` |
| Normalized interval value | `$normalizedValue` | `$v` |
| Normalized interval unit | `$normalizedUnit` | `$u` |
| DB connection object | `$connection` | `$dbConnection` |
| Vault token details (decoded) | `$details` | `$tokenDetails`, `$tokenDetailsJson` |
| Additional info from payment | `$additionalInfo` | `$ai` |
| Hash map of increment IDs | `$incrementIdMap` | `$incrementIds` |
| Hash map of chain keys | `$chainKeyMap` | `$chainKeys` |
| Latest subscription ID in chain | `$latestSubscriptionId` | `$latestSubId` |
| Sales order in loop | `$salesOrder` | `$o` |
| Scheme reference from payment | `$schemeRefFromPayment` | `$ai` |
| Cron resource model | `$resourceModel` | `$subscriptionResource` |
| Cron table name | `$tableName` | `$subscriptionTable` |

### 11.2 Constructor Style

All new and updated classes use **PHP 8.1 constructor property promotion** with
`private readonly` modifiers. Manual property declarations + constructor
assignments are removed.

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

### 11.3 Dead Code Removed

| File | What was removed |
|---|---|
| `Controller/Subscription/UpdatePayment.php` | Unused `$context` property; old-style manual property declarations replaced with promoted properties |
| `Controller/Subscription/Cancel.php` | Unused `Context $context` constructor parameter; manual property declarations replaced with promoted properties |
| `Controller/Adminhtml/Subscription/UpdatePayment.php` | Double blank line in `resolveSubscriptionIdFromOrderIncrement` |
| `Model/System/Utils/PaymentTokenUtil.php` | Empty constructor `__construct()` |
| `Model/Subscription/Order.php` | Extra blank lines between constant declarations and methods |
| `Model/SubscriptionOrder/SubscriptionOrderRepository.php` | Extra blank line between methods |
| `Gateway/Response/CommerceHub/SubscriptionDetailsHandler.php` | Manual property declarations replaced with promoted properties; extra blank line before `save()` |
| `Service/OrderPaymentContextBuilder.php` | Manual property declarations replaced with promoted properties; added `declare(strict_types=1)` |
| `Service/PaymentProcessor.php` | Premature `$resolvedExpiryMonth`/`$resolvedExpiryYear` declarations moved to point of use |

### 11.4 Whitespace Rules

- **One blank line** between methods — never two.
- **No trailing blank lines** at end of file.
- **No blank lines** between adjacent constant declarations.
- **One blank line** between the last constant and the first method.

