# Open Refund — Development Guide

> **Last updated:** April 22, 2026
> **Author:** Tyson Nguyen
>
> Everything a developer needs to understand, maintain, debug, or extend the Open Refund
> feature. Covers what it is, why it was built the way it was, every file involved,
> how the frontend and backend wire together, and how to test it.

---

## Table of Contents

1. [What Is an Open Refund?](#1-what-is-an-open-refund)
2. [The Critical API Rule](#2-the-critical-api-rule)
3. [The Two Payment Paths](#3-the-two-payment-paths)
4. [Why PaymentSession Cannot Be Used Directly](#4-why-paymentsession-cannot-be-used-directly)
5. [Full Request Flows](#5-full-request-flows)
   - [Vault Token Path](#51-vault-token-path)
   - [New Card (Hosted Fields) Path](#52-new-card-hosted-fields-path)
6. [Backend — File by File](#6-backend--file-by-file)
   - [Database Schema](#61-database-schema)
   - [Admin Configuration](#62-admin-configuration)
   - [ACL & Menu](#63-acl--menu)
   - [DI Wiring](#64-di-wiring)
   - [Model / API / Repository Layer](#65-model--api--repository-layer)
   - [Controllers](#66-controllers)
   - [Service Layer (Core Business Logic)](#67-service-layer-core-business-logic)
   - [Block Classes](#68-block-classes)
   - [UI Data Providers](#69-ui-data-providers)
7. [Frontend — File by File](#7-frontend--file-by-file)
   - [Layout XML](#71-layout-xml)
   - [Admin UI Components](#72-admin-ui-components)
   - [Templates](#73-templates)
   - [form.js — The Heart of the Frontend](#74-formjs--the-heart-of-the-frontend)
   - [Hosted Field Styling (CSS)](#75-hosted-field-styling-css)
8. [How the Pieces Connect](#8-how-the-pieces-connect)
   - [Page Load Sequence](#81-page-load-sequence)
   - [Customer → Token Reload (AJAX)](#82-customer--token-reload-ajax)
   - [Hosted Field Callbacks Explained](#83-hosted-field-callbacks-explained)
   - [Mask / Unmask Eye Icons](#84-mask--unmask-eye-icons)
   - [Submit: Vault Path](#85-submit-vault-path)
   - [Submit: New Card Path](#86-submit-new-card-path)
   - [Success Flash After Redirect](#87-success-flash-after-redirect)
9. [Complete File List](#9-complete-file-list)
10. [Configuration Reference](#10-configuration-reference)
11. [Design Decisions & Why](#11-design-decisions--why)
12. [Debugging Guide](#12-debugging-guide)
13. [QA Test Cases](#13-qa-test-cases)
14. [What Is Out of Scope](#14-what-is-out-of-scope)
15. [Changelog — Post-Initial-Guide Changes](#15-changelog--post-initial-guide-changes)

---

## 1. What Is an Open Refund?

A standard Magento credit memo refund is **linked** — it references a prior CommerceHub
charge by `referenceTransactionDetails`. CommerceHub uses that reference to reverse a
specific captured transaction.

An **Open Refund** (also called a standalone or unlinked refund) is a credit pushed
directly to a payment card **without referencing any prior transaction**. There is no
originating Magento order, no credit memo, and no `referenceTransactionDetails` in the
API request. The merchant is simply crediting a card.

**Access:** Admin only → **Sales → Open Refunds**. No storefront exposure.

**Currency:** All open refunds are **USD only**. `currency_code` is hardcoded to `USD`
in `Save.php` and `OpenRefundService`. There is no currency selector in the UI.

---

## 2. The Critical API Rule

The single structural difference between a credit memo refund and an open refund is
whether `referenceTransactionDetails` is present in the CH API request:

| Field | Credit Memo Refund | Open Refund |
|---|---|---|
| `referenceTransactionDetails` | ✅ Required | ❌ **Must be omitted** |
| `source` (PaymentToken) | ❌ Not included | ✅ **Required** |
| `amount` | ✅ | ✅ |
| `transactionDetails` | ✅ | ✅ |
| `merchantDetails` | ✅ | ✅ |

`OpenRefundService::buildRefundRequest()` simply never calls
`$refundRequest->setReferenceTransactionDetails(...)`. That omission is what makes
CommerceHub treat it as an unlinked refund.

---

## 3. The Two Payment Paths

The form supports two ways to provide the card to refund:

| Path | How it works |
|------|-------------|
| **Vault** | Admin selects a customer, picks one of their saved vault tokens. The service looks up the `VaultPaymentToken` record and builds a `PaymentToken` from its stored data. |
| **New Card** | Admin enters a card via hosted-field iframes. The card data never touches Magento's server — it goes directly from the browser to CommerceHub. CH returns a `PaymentSession` ID, which Magento exchanges for a `PaymentToken` via a separate tokenization call, then uses that token for the refund. |

---

## 4. Why PaymentSession Cannot Be Used Directly

> **The most important architectural note in this entire document.**

When hosted fields complete (`ch-adapter.submitCardForm()`), CommerceHub returns a
**PaymentSession** — a short-lived, single-use session ID that represents the captured
card data on CH's servers.

- **`/payments/v1/charges`** — accepts PaymentSession ✅
- **`/payments/v1/refunds` (referenced credit memo)** — accepts PaymentSession ✅
- **`/payments/v1/refunds` (open/unlinked)** — **rejects PaymentSession ❌**, requires PaymentToken

This is why `Controller/Adminhtml/OpenRefund/TokenizeCard.php` exists. It calls
`payments-vas/v1/tokens` server-side to exchange the session for a durable `PaymentToken`
before the refund is submitted. The resulting token fields (`tokenData`, `tokenSource`,
expiry, nameOnCard) are returned to the browser as JSON, then POSTed to `Save.php` as
`new_card_*` fields.

**Do not remove or bypass `TokenizeCard.php`. The refund will fail at CH.**

---

## 5. Full Request Flows

### 5.1 Vault Token Path

```
Admin: Sales → Open Refunds → Create New Open Refund
  ↓
Edit.php renders page
  ↓
Admin selects a Customer
  → form.js: customerComp.value change fires
  → GET fiserv/openRefund/getTokens?customer_id=X&form_key=...
  → GetTokens.php:
      iterates all stores
      calls PaymentTokenManagement::getVisibleAvailableTokens(customerId, storeId)
      (filters: is_active=1, is_visible=1, not expired, website scope)
      keeps only fiserv_commercehub tokens
      deduplicates by public_hash across stores
      returns { tokens: [{ value: public_hash, label: "VISA ending 4242 (exp 12/26)" }] }
  → form.js: repopulates vault_token_hash KO select
  ↓
Admin selects a token, enters amount + optional notes, clicks "Submit Refund"
  ↓
form.js: formComp.save() (our override)
  → formComp.validate()
      required-entry on customer_id
      validate-greater-than-zero on amount
      validate-currency-amount: /^\d+\.\d{1,2}$/ (e.g. 10.99, not 10 or 10.999)
  → doAjaxSave(saveUrl, indexUrl, { payment_source_type: 'vault' })
      reads KO provider data, adds form_key
      $.ajax POST to fiserv/openRefund/save
  ↓
Save.php:
  reads $postData['data'] (UI form namespace)
  validates amount > 0 and regex /^\d+\.\d{1,2}$/
  snapshots customer_name + customer_email via CustomerRepositoryInterface
  sets admin_user_id from AdminSession (server-side only — never from POST)
  sets currency_code = 'USD'
  calls OpenRefundService::submit($openRefund, $data)
  ↓
OpenRefundService::submit():
  1. checks open_refund_transaction_limit (throws if exceeded)
  2. buildVaultSource($formData, $customerId):
       loads VaultPaymentToken by public_hash + customer_id + is_active=1
       strips $$TS$$=... suffix from gateway_token
       parses expirationDate (MM/YY), tokenSource, nameOnCard from token_details JSON
       returns PaymentToken object
  3. buildRefundRequest($openRefund, $source):
       Amount { total: float, currency: 'USD' }
       MerchantDetails { merchantId, terminalId, merchantPartner }
       TransactionDetails {
         captureFlag: isOpenRefundCaptureFlag(),
         createToken: false,
         accountVerification: false,
         merchantTransactionId: substr(uniqid('or_', true), 0, 36)
       }
       Customer { merchantCustomerId, firstName, lastName, email } (if customerId set)
       RefundRequest { source, amount, transactionDetails, merchantDetails [, customer] }
       ← NO referenceTransactionDetails
  4. saves record: status=pending (before API call, so record exists even on crash)
  5. ChHttpAdapter::sendRequest($refundRequest, 'payments/v1/refunds')
  6. HTTP 2xx + transactionState='CAPTURED':
       status=success, store transactionId, maskedCard (source.card.last4)
     anything else:
       status=failed, throw LocalizedException with CH error message
  ↓
Save.php returns JSON:
  success: { error: false, message, transactionId, entityId }
  failure: { error: true, message }
  ↓
form.js reads response:
  error=false → sessionStorage.setItem('openRefundSuccess', msg) → redirect to index
  error=true  → showBanner(false, message) — form stays open, admin can retry
  ↓
Index page: index-banner.phtml reads sessionStorage → shows green success banner
```

### 5.2 New Card (Hosted Fields) Path

Steps 1–2 (navigate, page load) are identical to vault. Differences start when the admin
picks "Enter New Card":

```
Admin selects "Enter New Card" as payment_source_type
  ↓
form.js: sourceComp.value subscription fires
  vault_token_hash hidden (tokenComp.visible(false))
  #hosted-fields-wrap shown
  mountHostedFields() called (if fieldsReady=false):
    chAdapter.initialize(paymentConfig, noop, noop,
                         onCardBrandChange, onFieldValidity, onFieldFocus)
    chAdapter.instantiateIframe(resolve, reject)
    resolve:
      fieldsReady = true
      bindMaskToggle() — wires eye icon click handlers
    reject:
      showHostedFieldError(...)
  ↓
Admin types card details in iframes
  SDK fires onCardBrandChange  → brand icon div class updated
  SDK fires onFieldValidity    → green/red border + error text per field
  SDK fires onFieldFocus       → sdc-focused-field class on active frame
  Admin can click eye icon     → chAdapter.unmask('cardNumber') / mask()
  ↓
Admin clicks "Submit Refund"
  ↓
form.js: formComp.save() → validate() → doNewCardSave()
  check fieldsReady (if false: show error, abort)
  check tokenising lock (if true: abort — prevents double submit)
  tokenising = true
  processStart spinner
  ↓
  chAdapter.submitCardForm(storeUrl, successCb, errorCb)
    iframes POST card data directly to CommerceHub
    CH returns a PaymentSession ID (session_id)
  ↓
  successCb(session_id):
    $.ajax POST fiserv/openRefund/tokenizeCard { session_id, form_key }
    ↓
    TokenizeCard.php:
      calls TokenizationRequest::tokenizeSession(session_id)
        → CH payments-vas/v1/tokens
        ← { paymentTokens[0].tokenData, tokenSource,
             source.card.expirationMonth/Year/last4/nameOnCard }
      returns { error: false, tokenData, tokenSource,
                expMonth, expYear, last4, nameOnCard }
    ↓
    tokenising = false, processStop
    doAjaxSave(saveUrl, indexUrl, {
      payment_source_type:   'new_card',
      new_card_token_data:   res.tokenData,
      new_card_token_source: res.tokenSource,
      new_card_exp_month:    res.expMonth,
      new_card_exp_year:     res.expYear,
      new_card_name_on_card: res.nameOnCard
    })
  ↓
  errorCb(err):
    tokenising=false, processStop
    showHostedFieldError(...)
    fieldsReady=false, unbind mask handlers, remount hosted fields
  ↓
Save.php:
  explicitly merges top-level new_card_* POST fields into $data:
    foreach ['payment_source_type', 'new_card_token_data', ...] as $key
      if isset($postData[$key]) && !isset($data[$key])
        $data[$key] = $postData[$key]
  (these fields are POSTed outside the data[...] namespace)
  → calls OpenRefundService::submit($openRefund, $data)
  ↓
OpenRefundService::submit():
  buildTokenDataSource($formData):
    reads new_card_token_data (throws if empty)
    reads new_card_token_source, exp fields, nameOnCard
    constructs PaymentToken object
  → continues identical to vault path from buildRefundRequest() onward
```

---

## 6. Backend — File by File

### 6.1 Database Schema

**`etc/db_schema.xml`** — Magento declarative schema. No setup patch exists or is needed.

**Table:** `fiserv_open_refund`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `entity_id` | int unsigned, auto-increment | No | — | PK |
| `amount` | decimal(12,2) | No | — | Refund amount (USD) |
| `currency_code` | varchar(10) | No | `USD` | Always USD; not in UI |
| `customer_id` | int unsigned | Yes | NULL | FK → customer_entity |
| `customer_email` | varchar(255) | Yes | NULL | Snapshotted at submit |
| `customer_name` | varchar(255) | Yes | NULL | Snapshotted at submit |
| `admin_user_id` | int unsigned | Yes | NULL | From AdminSession only |
| `status` | varchar(20) | No | `pending` | pending / success / failed |
| `transaction_id` | varchar(255) | Yes | NULL | CH transaction ID on success |
| `masked_card` | varchar(20) | Yes | NULL | Last 4 digits from CH response |
| `notes` | text | Yes | NULL | Free-text admin notes |
| `created_at` | timestamp | No | CURRENT_TIMESTAMP | Auto-set on insert |

> **Columns removed post-initial build:** `reference_transaction_id` and `order_increment_id`
> were removed because open refunds are standalone transactions with no order linkage.
> See [§15](#15-changelog--post-initial-guide-changes) for the full story.

---

### 6.2 Admin Configuration

**`etc/config.xml`** sets the default:
```xml
<open_refund_capture_flag>1</open_refund_capture_flag>
```

**`etc/adminhtml/system.xml`** exposes a Yes/No toggle in the Credit/Debit Cards group:
```
Stores → Configuration → Sales → Payment Methods → Fiserv → Credit/Debit Cards
  → Open Refund Capture Flag
    Yes (default) = captureFlag: true  → immediate settlement
    No            = captureFlag: false → authorisation only
```

A second hidden config key `open_refund_transaction_limit` can be set directly in the DB
(`config_path = payment/fiserv_commercehub/open_refund_transaction_limit`). When non-zero,
the service rejects amounts exceeding it with a user-facing error.

**`Gateway/Config/CommerceHub/Config.php`** accessors:
```php
const KEY_OPEN_REFUND_CAPTURE_FLAG = 'open_refund_capture_flag';

public function isOpenRefundCaptureFlag($storeId = null): bool
{
    return (bool) $this->getValue(self::KEY_OPEN_REFUND_CAPTURE_FLAG, $storeId);
}
```

---

### 6.3 ACL & Menu

**`etc/acl.xml`:**
```xml
<resource id="Fiserv_Payments::open_refunds" title="Open Refunds" sortOrder="30">
    <resource id="Fiserv_Payments::open_refunds_manage"
              title="Create / Edit Open Refunds" sortOrder="10"/>
</resource>
```

**`etc/adminhtml/menu.xml`:** Adds **Sales → Open Refunds** (sortOrder 100).

| ACL Resource | Controls access to |
|---|---|
| `Fiserv_Payments::open_refunds` | Index controller (view grid) |
| `Fiserv_Payments::open_refunds_manage` | Edit, Save, GetTokens, TokenizeCard controllers |

---

### 6.4 DI Wiring

**`etc/di.xml`** — two preferences:
```xml
<preference for="Fiserv\Payments\Api\OpenRefund\OpenRefundRepositoryInterface"
            type="Fiserv\Payments\Model\OpenRefundRepository"/>
<preference for="Fiserv\Payments\Api\Data\OpenRefund\OpenRefundSearchResultInterface"
            type="Fiserv\Payments\Model\OpenRefund\OpenRefundSearchResult"/>
```

No gateway virtual types or command pools needed — the service calls `ChHttpAdapter` directly.

---

### 6.5 Model / API / Repository Layer

#### `Api/Data/OpenRefund/OpenRefundInterface.php`
Declares all `KEY_*` string constants (column names) and getter/setter method signatures.
Every field in the DB table has a corresponding `KEY_*` constant and a pair of methods.

#### `Model/OpenRefund.php`
Implements `OpenRefundInterface`, extends `AbstractModel`.

```php
const STATUS_PENDING = 'pending';
const STATUS_SUCCESS = 'success';
const STATUS_FAILED  = 'failed';
```

All getters/setters are one-liners:
```php
public function getAmount() { return $this->getData(self::KEY_AMOUNT); }
public function setAmount($v) { return $this->setData(self::KEY_AMOUNT, $v); }
```

> **Why explicit methods?** PHP enforces interface contracts at compile time. Although
> `AbstractModel::__call` provides dynamic `getData/setData` at runtime, **removing the
> explicit methods causes a fatal error** ("Class contains N abstract methods"). They must
> stay. One-liners keep the file concise while satisfying the requirement.

#### `Model/ResourceModel/OpenRefund.php`
Extends `AbstractDb`. Maps the model to `fiserv_open_refund` with PK `entity_id`.

#### `Model/ResourceModel/OpenRefund/Collection.php`
Extends `AbstractCollection`, implements `SearchResultInterface` (required by the grid data
provider). Provides `getAggregations`, `getTotalCount`, etc.

#### `Model/OpenRefundRepository.php`
Full CRUD. PHP 8 constructor property promotion. Wraps `CouldNotSaveException` /
`CouldNotDeleteException` / `NoSuchEntityException` appropriately.
`getList()` uses `CollectionProcessorInterface` to apply `SearchCriteria` filters, sorting, and pagination to the collection.

#### `Model/OpenRefund/OpenRefundSearchResult.php`
Extends `SearchResults`. One-line class body — Magento's base class does all the work.

#### `Model/Source/OpenRefund/Status.php`
`OptionSourceInterface` returning `pending / success / failed` labels. Used by the grid
status column filter dropdown.

---

### 6.6 Controllers

All controllers: extend `Magento\Backend\App\Action`, use PHP 8 constructor property
promotion, and have proper `_isAllowed()` methods that check the real ACL resource.

#### `Index.php` — `fiserv/openRefund/index`
Sets active menu to `open_refunds`, sets page title "Open Refunds", renders the grid page.

#### `Edit.php` — `fiserv/openRefund/edit`
Sets page title "Create New Open Refund", renders the form page. Create-only — no
edit-existing-record support.

#### `Save.php` — `fiserv/openRefund/save` (POST) — **returns JSON**
Key responsibilities:
- Reads `$postData['data']` (Magento UI form namespace).
- Merges top-level `new_card_*` POST fields into `$data` (posted outside the `data[...]`
  namespace and would otherwise be missed).
- Server-side validates amount format: `/^\d+\.\d{1,2}$/`
- Snapshots customer name + email from `CustomerRepositoryInterface` (never trusts POST).
- Sets `admin_user_id` from `AdminSession::getUser()` (never from POST — security).
- Hardcodes `currency_code = 'USD'`.
- Calls `OpenRefundService::submit()`.
- Returns `{ error: false/true, message, transactionId, entityId }`.

#### `GetTokens.php` — `fiserv/openRefund/getTokens` (GET/AJAX)
Loops all stores, calls `getVisibleAvailableTokens(customerId, storeId)` on each,
filters to `fiserv_commercehub`, deduplicates by `public_hash`, builds label from
`token_details` JSON. Returns `{ tokens: [...] }`.

Using `getVisibleAvailableTokens` (not a raw DB query) ensures expired, invisible, and
wrong-website tokens are automatically excluded by Magento's vault logic.

#### `TokenizeCard.php` — `fiserv/openRefund/tokenizeCard` (POST/AJAX)
Accepts `session_id`. Calls `TokenizationRequest::tokenizeSession($sessionId)` which
posts to CH `payments-vas/v1/tokens`. On success returns the token fields. On failure
returns `{ error: true, message }`. See [§4](#4-why-paymentsession-cannot-be-used-directly).

---

### 6.7 Service Layer (Core Business Logic)

**`Model/Service/OpenRefund/OpenRefundService.php`**

Constructor (PHP 8 property promotion):
```php
public function __construct(
    private readonly Config $config,
    private readonly ChHttpAdapter $httpAdapter,
    private readonly OpenRefundRepository $openRefundRepository,
    private readonly PaymentTokenRepositoryInterface $paymentTokenRepository,
    private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    private readonly MultiLevelLogger $logger
) {}
```

**`submit(OpenRefund $openRefund, array $formData): void`**

1. **Transaction limit check** — reads `open_refund_transaction_limit` config. Throws
   `LocalizedException` if exceeded. (0 or unset = no limit.)

2. **Source resolution** based on `$formData['payment_source_type']`:

   `buildVaultSource(array $formData, int $customerId): PaymentToken`
   - Queries `vault_payment_token` by `public_hash + customer_id + is_active=1`.
   - Strips `$$TS$$=...` suffix from `gateway_token` (pattern `TS_SUFFIX_PATTERN`).
   - Parses `expirationDate` (tries `MM/YY` format first, falls back to separate `expMonth`/`expYear` keys).
   - Sets `declineDuplicates: false` on the token object.
   - Returns a fully populated `PaymentToken` object.

   `buildTokenDataSource(array $formData): PaymentToken`
   - Reads `new_card_token_data`, `new_card_token_source`, `new_card_exp_month`,
     `new_card_exp_year`, `new_card_name_on_card` from `$formData`.
   - Throws `LocalizedException` if `tokenData` is empty.
   - Returns a `PaymentToken` built from those fields.

3. **`buildRefundRequest(OpenRefund $openRefund, $source): RefundRequest`**
   - No `referenceTransactionDetails` — this is what makes it an open refund.
   - `merchantTransactionId = substr(uniqid('or_', true), 0, 36)` — unique per request.
   - `captureFlag` from `Config::isOpenRefundCaptureFlag()`.
   - `createToken: false`, `accountVerification: false`.
   - Includes `MerchantDetails` via `MerchantPartnerHelper::createMerchantPartner($config)`.
   - Includes `Customer` object if `customerId` is set.

4. **Pre-call save** — saves record as `status=pending` before the HTTP call so the
   record exists even if the process crashes mid-flight.

5. **API call** — `ChHttpAdapter::sendRequest($refundRequest, 'payments/v1/refunds')`.

6. **Response handling:**
   - HTTP 2xx **and** `gatewayResponse.transactionState === 'CAPTURED'` → success.
     Saves `transactionId` (`gatewayResponse.transactionProcessingDetails.transactionId`) and `maskedCard` (last4 from `source.card.last4`).
   - Anything else → `status=failed`, throws `LocalizedException` with the CH error message.
     Error message resolution order: `error[0].message` → `errors[0].message` → `transactionState` → `'Unknown error'`.

**Logging convention** — no order ID context (open refunds have no associated order):

| Method | When |
|--------|------|
| `logInfo(1, ...)` | Top-level milestones (initiating, sending to CH) |
| `logInfo(2, ...)` | Amount, customer ID, transaction ID on success |
| `logDebug(3, ...)` | Full request/response JSON (pretty-printed) |
| `logError(1/2, ...)` | Non-CAPTURED state |
| `logCritical(1/2, ...)` | HTTP transport exception |

---

### 6.8 Block Classes

#### `Block/Adminhtml/OpenRefund/CreateButton.php`
Extends `Magento\Backend\Block\Widget\Button`. Sets label, onclick (`setLocation` to the
edit URL), and CSS class. Rendered in the grid page toolbar via the listing UI component's
`<settings><buttons>` block.

#### `Block/Adminhtml/OpenRefund/FormInit.php`
Extends `Magento\Backend\Block\Template`.

**Key method: `getPaymentConfigJson(): string`**

Builds and JSON-serializes the config object consumed by `form.js`:

```json
{
  "environment":   "...",
  "merchantId":    "...",
  "apiKey":        "...",
  "terminalId":    "...",
  "storeUrl":      "...",
  "formConfig":    { "fields": {...}, "css": {...}, "font": {...} },
  "invalidFields": { "cardNumber": "...", "nameOnCard": "...", ... }
}
```

- `formConfig` — built by `ConfigProvider::buildFormConfig(KEY_SDC_ADMIN, $storeId)`.
  Controls the appearance (styling, placeholders) of the hosted-field iframes.
- `invalidFields` — built by `ConfigProvider::getInvalidFieldMessages(KEY_SDC_ADMIN, $storeId)`.
  Error message strings shown below each field when validation fails.
  **This was missing in the initial build and caused error messages to appear blank.**

> **No custom block class is needed for the Submit button.**
> `Block/Adminhtml/OpenRefund/Edit/SaveButton.php` was deleted — a class that only sets
> `$_template` adds zero value. `Magento\Backend\Block\Template` handles it natively.

---

### 6.9 UI Data Providers

#### `Model/Ui/OpenRefund/ListingDataProvider.php`
Extends Magento's `DataProvider`. **Overrides `getData()`** to use the collection directly
instead of the parent's search-criteria flow. This bypasses a Magento bug where
`SearchCriteria::getFilterGroups()` returns `null` on initial load, causing a
`foreach on null` PHP fatal error. Also overrides `getSearchResult()` for the same reason.

Formats `amount` as `$##.##` (e.g. `$10.99`) inline before returning items.

#### `Model/Ui/OpenRefund/FormDataProvider.php`
Extends `AbstractDataProvider`. Returns `[]` for the create flow. Supports loading by
`entity_id` query param for a possible future edit flow.

#### `Model/Ui/OpenRefund/CustomerOptions.php`
Queries all customers via `CustomerRepositoryInterface::getList()`. Label format:
`"John Smith <john@example.com>"`. Result is cached in `$this->options` after first call.

#### `Model/Ui/OpenRefund/CustomerTokenOptions.php`
Returns a single placeholder option: `"-- Select a customer first --"`. Actual token
options are loaded via AJAX (`GetTokens.php`) after the admin picks a customer.

---

## 7. Frontend — File by File

### 7.1 Layout XML

#### `view/adminhtml/layout/fiserv_openrefund_index.xml`
- Loads `fiserv_open_refund_listing` UI component in the content container.
- Adds `index-banner.phtml` (via `Magento\Backend\Block\Template`) to the content area.
  This template renders the sessionStorage-based flash success banner.

#### `view/adminhtml/layout/fiserv_openrefund_edit.xml`
Three additions to the page:
1. **`page.actions.toolbar`** — `save-button.phtml` via `Magento\Backend\Block\Template`
   (no custom class). This is the "Submit Refund" button in the page header.
2. **`content` container** — `fiserv_open_refund_form` UI component.
3. **`content` container** (after the form) — `form-init.phtml` via `FormInit` block.
   This injects the JS initializer and the banner div.

---

### 7.2 Admin UI Components

#### `view/adminhtml/ui_component/fiserv_open_refund_listing.xml`

Standard Magento grid. Data source: `ListingDataProvider`.

Columns: `entity_id`, `created_at` (dateRange filter), `customer_name`, `customer_email`,
`amount` (pre-formatted as `$##.##`), `masked_card`, `status` (select filter), `transaction_id`.

#### `view/adminhtml/ui_component/fiserv_open_refund_form.xml`

Two fieldsets. Submit is intercepted by `form.js` — the UI form never POSTs natively.

**Fieldset 1 — Refund Details:**

| Field | Element | Validation |
|-------|---------|------------|
| `customer_id` | select (CustomerOptions) | required-entry |
| `amount` | input | required-entry, validate-greater-than-zero, validate-currency-amount |
| `notes` | textarea | — |

**Fieldset 2 — Payment Source:**

| Field | Element | Notes |
|-------|---------|-------|
| `payment_source_type` | select | `vault` / `new_card` |
| `vault_token_hash` | select (CustomerTokenOptions) | AJAX-reloaded on customer change |
| `hosted_fields_container` | htmlContent | Renders `hosted-fields.phtml` |

The `htmlContent` block uses `Magento\Backend\Block\Template` with
`template="Fiserv_Payments::open-refund/hosted-fields.phtml"`.

---

### 7.3 Templates

All in `view/adminhtml/templates/open-refund/`.

#### `form-init.phtml` — rendered by `FormInit` block after the UI form

Two things this template outputs:

1. **The banner div** — hidden until a save attempt; shown by `form.js` on success or error:
   ```html
   <div id="open-refund-banner" style="display:none; margin-bottom:12px;"></div>
   ```

2. **The JS initializer:**
   ```html
   <script type="text/x-magento-init">
   {
       "*": {
           "Fiserv_Payments/js/open-refund/form": {
               "getTokensUrl":    "...",
               "saveUrl":         "...",
               "indexUrl":        "...",
               "tokenizeCardUrl": "...",
               "paymentConfig":   { environment, merchantId, apiKey, terminalId,
                                    storeUrl, formConfig, invalidFields }
           }
       }
   }
   </script>
   ```

#### `hosted-fields.phtml` — rendered inside the Payment Source fieldset

Loads `ch-form-admin.css` and outputs the `sdc-*` DOM structure that mirrors
`order/form/cc.phtml` in the admin order-create flow:

```
#hosted-fields-wrap (hidden by default; shown when "Enter New Card" selected)
  #sdc-container
    .sdc-row
      #sdc-card-number-frame.sdc-field-frame.sdc-text
        #sdc-card-brand-icon.sdc-card-brand-icon    ← card logo (JS swaps CSS class)
        #fiserv_commercehub-card-number.sdc-field   ← IFRAME MOUNT POINT
        #sdc-unmask-number.sdc-unmasking-icon       ← eye-open icon
        #sdc-mask-number.sdc-masking-icon.sdc-hidden ← eye-closed icon
      #sdc-card-number-invalid-message.sdc-error-message.sdc-hidden
    .sdc-row
      #sdc-card-name-frame.sdc-field-frame.sdc-text
        #fiserv_commercehub-name-on-card.sdc-field  ← IFRAME MOUNT POINT
      #sdc-card-name-invalid-message.sdc-error-message.sdc-hidden
    .sdc-row
      #sdc-security-code-frame.sdc-field-frame.sdc-text
        #fiserv_commercehub-security-code.sdc-field ← IFRAME MOUNT POINT
        #sdc-unmask-security.sdc-unmasking-icon
        #sdc-mask-security.sdc-masking-icon.sdc-hidden
      #sdc-security-code-invalid-message.sdc-error-message.sdc-hidden
    .sdc-column  (flex row — side-by-side expiry)
      .sdc-row
        #sdc-exp-month-frame.sdc-field-frame.sdc-dropdown
          #fiserv_commercehub-expiration-month.sdc-field ← IFRAME MOUNT POINT
        #sdc-exp-month-invalid-message
      .sdc-row
        #sdc-exp-year-frame.sdc-field-frame.sdc-dropdown
          #fiserv_commercehub-expiration-year.sdc-field  ← IFRAME MOUNT POINT
        #sdc-exp-year-invalid-message
```

The `fiserv_commercehub-*` IDs are the mount points that `ch-adapter.instantiateIframe()`
targets. **Do not change these IDs** — the SDK uses them by name.

#### `index-banner.phtml` — rendered on the grid index page

Inline script reads `sessionStorage.getItem('openRefundSuccess')`, shows a success banner,
then removes the item. This is how the "flash after redirect" works without Magento session
messages (which are unreliable with JSON-redirect flows).

#### `save-button.phtml` — rendered in the page toolbar

A single `<button>` with `onclick` that calls `formComp.save()` via `uiRegistry`. Uses
`Magento\Backend\Block\Template` as its block class in the layout XML — no PHP class needed.

---

### 7.4 form.js — The Heart of the Frontend

**`view/adminhtml/web/js/open-refund/form.js`**

AMD module with entry point `return function (config) { ... }`. Loaded via
`text/x-magento-init` in `form-init.phtml`.

Dependencies: `jquery`, `uiRegistry`, `Fiserv_Payments/js/ch-adapter`,
`Magento_Ui/js/modal/alert`, `mage/validation`.

#### Module-level state (closures — not global)

```javascript
var fieldsReady   = false;  // true after iframes successfully mount
var tokenising    = false;  // lock prevents double-submit on new card path
var paymentConfig = {};     // from config.paymentConfig (injected by FormInit)
var storeUrl      = '';     // paymentConfig.storeUrl — needed by submitCardForm
```

#### UI Registry paths

These strings map to the KnockoutJS observable components in the UI form XML.
If the XML field names or fieldset names change, update these:

```javascript
var FORM_NS          = 'fiserv_open_refund_form.fiserv_open_refund_form';
var CUSTOMER_COMP    = FORM_NS + '.refund_details.customer_id';
var TOKEN_COMP       = FORM_NS + '.payment_source.vault_token_hash';
var SOURCE_TYPE_COMP = FORM_NS + '.payment_source.payment_source_type';
```

#### Custom validator

```javascript
$.validator.addMethod('validate-currency-amount', function (value) {
    if (value === '' || value === null) { return true; }
    return /^\d+\.\d{1,2}$/.test(value.trim());
}, '...');
```

Valid: `10.99`, `10.9`, `0.01`. Invalid: `10` (integer), `10.999` (3 dp).
Same rule is enforced server-side in `Save.php`.

#### Hosted field callbacks — why they exist

`chAdapter.initialize()` takes 6 arguments. Arguments 4–6 are callbacks the CH SDK calls
via `postMessage` when events happen inside the cross-origin iframes. Originally these were
all no-ops, which meant:
- Card brand icon never changed ✗
- Fields never showed green/red validation borders ✗
- Eye icons were wired in HTML but did nothing ✗

The current implementations:

**`onCardBrandChange(brand)`** — Maps SDK brand string (`'visa'`, `'mastercard'`, etc.)
to a CSS class on `#sdc-card-brand-icon`. Resets to blank icon if brand is null/unknown.

**`onFieldValidity(data)`** — Called whenever a field's validity state changes:
- `data.isValid === true` → add `sdc-valid-field` (green border), hide error span.
- `data.shouldShowError === true` → add `sdc-error-field` (red border), set error span
  text from `paymentConfig.invalidFields[data.field]`, show error span.
- Neither → remove both border classes, hide error span.

`data.shouldShowError` is only `true` once the user has blurred the field — the SDK
doesn't show errors while the user is still typing.

**`onFieldFocus(fieldName)`** — Adds `sdc-focused-field` (thicker border) when the field
is focused, removes it when not.

#### `bindMaskToggle()` — called after iframes successfully mount

```javascript
function bindMaskToggle() {
    $('#sdc-unmask-number').on('click', function () {
        chAdapter.unmask('cardNumber');
        $('#sdc-unmask-number').addClass('sdc-hidden');
        $('#sdc-mask-number').removeClass('sdc-hidden');
    });
    // ... mirror for mask, and for security code
}
```

Before registering, all four handlers are `.off('click')`-ed to prevent stacking if
hosted fields are re-armed after a failure.

#### `initSaveWrapper()` — overrides Magento's form save

`formComp.save()` is replaced so form.js controls what happens on submit instead of
Magento's default behavior (which would POST the form data as a full page request):

```javascript
registry.async(FORM_NS)(function (formComp) {
    formComp.save = function () {
        formComp.validate();
        if (formComp.additionalInvalid ||
            (formComp.source && formComp.source.get('params.invalid'))) {
            return;
        }
        registry.async(SOURCE_TYPE_COMP)(function (sourceComp) {
            if (sourceComp.value() === 'new_card') {
                doNewCardSave(saveUrl, indexUrl, tokenizeCardUrl);
            } else {
                doAjaxSave(saveUrl, indexUrl, { payment_source_type: 'vault' });
            }
        });
    };
});
```

#### Full function reference

| Function | Behaviour |
|----------|-----------|
| `getFormKey()` | Returns `$('input[name="form_key"]').val()` |
| `setTokenOptions(tokens)` | Rebuilds KO options on vault_token_hash select. Shows "No saved cards found" if empty. |
| `showBanner(isSuccess, text)` | Shows `#open-refund-banner` with success/error class, scrolls to it. |
| `showHostedFieldError(msg)` | Calls `showBanner(false, ...)` + `console.error`. |
| `onCardBrandChange(brand)` | Updates `#sdc-card-brand-icon` CSS class. |
| `onFieldValidity(data)` | Applies green/red borders and error text per field. |
| `onFieldFocus(fieldName)` | Toggles `sdc-focused-field` on the field's frame div. |
| `bindMaskToggle()` | Wires `.off().on('click')` for all 4 eye icon divs. |
| `mountHostedFields()` | Calls `chAdapter.initialize()` then `instantiateIframe()`. Sets `fieldsReady=true` and calls `bindMaskToggle()` on success. |
| `initSourceTypeToggle()` | Subscribes to `sourceComp.value`. Shows/hides vault select and hosted fields accordingly. |
| `initCustomerChangeListener(url)` | Subscribes to `customerComp.value`. Fetches token list and calls `setTokenOptions`. |
| `initSaveWrapper(...)` | Overrides `formComp.save()`. |
| `doAjaxSave(saveUrl, indexUrl, extraData)` | POSTs to save controller via `$.ajax`. Handles JSON response — redirect on success, banner on error, re-arm hosted fields on new card error. |
| `doNewCardSave(saveUrl, indexUrl, tokenizeCardUrl)` | `tokenising` lock → `submitCardForm` → `tokenizeCard` AJAX → `doAjaxSave`. Resets on all failure paths. |

---

### 7.5 Hosted Field Styling (CSS)

**`view/base/web/css/ch-form-admin.css`** — shared with the admin order-create flow.
Loaded inline in `hosted-fields.phtml` via `<link>`. Not created by this feature —
consumed by it.

Key classes used by the hosted field DOM:

| Class | Role |
|-------|------|
| `.sdc-row` | Flex column — one row per field |
| `.sdc-column` | Flex row — for side-by-side expiry month/year |
| `.sdc-field-frame` | Border box wrapping each iframe |
| `.sdc-field` | The iframe mount point div (40px height) |
| `.sdc-text` | 400px wide frame (card number, name, CVV) |
| `.sdc-dropdown` | 215px wide frame (expiry) |
| `.sdc-card-brand-icon` | Card logo — background-image swapped by `onCardBrandChange` |
| `.sdc-unmasking-icon` / `.sdc-masking-icon` | Eye icons for unmask toggle |
| `.sdc-hidden` | `display:none` — toggled between icon pairs |
| `.sdc-valid-field` | Green border — set by `onFieldValidity` |
| `.sdc-error-field` | Red border + bottom highlight — set by `onFieldValidity` |
| `.sdc-focused-field` | Thicker border — set by `onFieldFocus` |
| `.sdc-error-message` | Red text below frame — shown/hidden by `onFieldValidity` |

---

## 8. How the Pieces Connect

### 8.1 Page Load Sequence

```
1. Browser → fiserv/openRefund/edit
2. Edit.php → PageFactory::create()
3. fiserv_openrefund_edit.xml processed:
   │
   ├─ page.actions.toolbar:
   │    Magento\Backend\Block\Template + save-button.phtml
   │    → "Submit Refund" button in page header
   │
   ├─ content:
   │    fiserv_open_refund_form UI component
   │    ├─ FormDataProvider: returns []
   │    ├─ Fieldset: Refund Details
   │    │    customer_id select → CustomerOptions queries all customers
   │    │    amount input, notes textarea
   │    └─ Fieldset: Payment Source
   │         payment_source_type select
   │         vault_token_hash select → CustomerTokenOptions (placeholder)
   │         htmlContent → hosted-fields.phtml
   │           ch-form-admin.css loaded
   │           #hosted-fields-wrap HIDDEN
   │
   └─ content (after form):
        FormInit block + form-init.phtml
        ├─ <div id="open-refund-banner" style="display:none">
        └─ text/x-magento-init → form.js loaded with full paymentConfig

4. form.js entry point fires:
   ├─ paymentConfig = config.paymentConfig
   ├─ storeUrl = paymentConfig.storeUrl
   ├─ initCustomerChangeListener() → subscribe to customerComp.value KO observable
   ├─ initSourceTypeToggle()       → subscribe to sourceComp.value KO observable
   └─ initSaveWrapper()            → override formComp.save() once FORM_NS resolves
```

---

### 8.2 Customer → Token Reload (AJAX)

```
Admin picks a customer from the dropdown
  ↓
customerComp.value KO observable fires
  ↓
form.js:
  fetch(getTokensUrl + '?customer_id=X&form_key=...')
  ↓
GetTokens.php:
  for each store:
    getVisibleAvailableTokens(customerId, storeId)
    filter: payment_method_code === 'fiserv_commercehub'
    deduplicate on public_hash
    build label from token_details JSON
  return { tokens: [{ value: hash, label: "VISA ending 4242 (exp 12/26)" }] }
  ↓
form.js: setTokenOptions(tokens)
  registry.async(TOKEN_COMP) resolves to vault_token_hash KO component
  if setOptions() exists: tokenComp.setOptions(koOptions)
  else: tokenComp.options(koOptions)
  tokenComp.value('') → reset selection
```

---

### 8.3 Hosted Field Callbacks Explained

`chAdapter.initialize(paymentConfig, cb1, cb2, cb3, cb4, cb5)` — the SDK uses these
to report iframe events back to the host page (cross-origin, via `postMessage` internally):

| Position | form.js handler | Trigger |
|----------|----------------|---------|
| cb1 iframeReady | `function(){}` (no-op) | Iframes loaded; handled via Promise `.then()` instead |
| cb2 iframeValid | `function(){}` (no-op) | Not needed for refund flow |
| cb3 cardBrandChange | `onCardBrandChange(brand)` | User types a card number; SDK detects brand |
| cb4 fieldValidity | `onFieldValidity(data)` | Field value changes; SDK reports valid/invalid |
| cb5 fieldFocus | `onFieldFocus(fieldName)` | Field focused/blurred |

**`onFieldValidity` data shape:**
```javascript
{
  field: 'cardNumber' | 'nameOnCard' | 'securityCode' | 'expirationMonth' | 'expirationYear',
  isValid: boolean,
  shouldShowError: boolean  // only true after the user has blurred the field once
}
```

Error text is sourced from `paymentConfig.invalidFields[field]` — injected by
`FormInit::getPaymentConfigJson()` from `ConfigProvider::getInvalidFieldMessages()`.

---

### 8.4 Mask / Unmask Eye Icons

The eye icons in the DOM are CSS-background-image divs (not `<img>` tags or SVGs).
After `instantiateIframe` resolves, `bindMaskToggle()` wires them:

```
#sdc-unmask-number  click → chAdapter.unmask('cardNumber')
                           → #sdc-unmask-number.addClass('sdc-hidden')
                           → #sdc-mask-number.removeClass('sdc-hidden')

#sdc-mask-number    click → chAdapter.mask('cardNumber')
                           → #sdc-unmask-number.removeClass('sdc-hidden')
                           → #sdc-mask-number.addClass('sdc-hidden')
```

Same pattern for `#sdc-unmask-security` / `#sdc-mask-security` with `'securityCode'`.

On any re-arm (failure path), `.off('click')` is called on all four before `mountHostedFields()`
to prevent stacked duplicate handlers.

---

### 8.5 Submit: Vault Path

```
formComp.save()
  → formComp.validate()
  → doAjaxSave(saveUrl, indexUrl, { payment_source_type: 'vault' })
      provider = registry.get('...fiserv_open_refund_form_data_source')
      data = $.extend({}, provider.get('data'), { payment_source_type: 'vault' })
      data.form_key = getFormKey()
      $.ajax POST saveUrl
        ← { error: false, message, transactionId, entityId }
          sessionStorage.setItem('openRefundSuccess', ...)
          window.location.href = indexUrl
        ← { error: true, message }
          showBanner(false, message) — form stays open
        ← HTTP error
          showBanner(false, 'An unexpected error occurred...')
```

---

### 8.6 Submit: New Card Path

```
formComp.save()
  → formComp.validate()
  → doNewCardSave(saveUrl, indexUrl, tokenizeCardUrl)
      if !fieldsReady → showHostedFieldError, return
      if tokenising   → return (prevents double-submit)
      tokenising = true, processStart

      chAdapter.submitCardForm(storeUrl, successCb, errorCb)
        ↓ success: sessionId (PaymentSession from CH)
        $.ajax POST tokenizeCardUrl { session_id, form_key }
          TokenizeCard.php → TokenizationRequest::tokenizeSession(sessionId)
          CH payments-vas/v1/tokens → PaymentToken
          ← { error: false, tokenData, tokenSource, expMonth, expYear, last4, nameOnCard }
            tokenising=false, processStop
            doAjaxSave(saveUrl, indexUrl, {
              payment_source_type: 'new_card',
              new_card_token_data, new_card_token_source,
              new_card_exp_month, new_card_exp_year, new_card_name_on_card
            })
          ← { error: true, message }
            tokenising=false, processStop
            showBanner error
            fieldsReady=false, unbind masks, mountHostedFields()

        ↓ error: submitCardForm failed
          tokenising=false, processStop
          showHostedFieldError
          fieldsReady=false, unbind masks, mountHostedFields()
```

---

### 8.7 Success Flash After Redirect

Magento's standard `messageManager` session messages don't survive a JSON-redirect flow
reliably (they get consumed by other AJAX handlers or lost). Solution:

**On success (form.js):**
```javascript
sessionStorage.setItem('openRefundSuccess', $.mage.__('Open refund submitted successfully.'));
window.location.href = indexUrl;
```

**On index page load (index-banner.phtml inline script):**
```javascript
var msg = sessionStorage.getItem('openRefundSuccess');
if (msg) {
    sessionStorage.removeItem('openRefundSuccess');
    var el = document.getElementById('open-refund-index-banner');
    el.className = 'message message-success success';
    el.textContent = msg;
    el.style.display = 'block';
}
```

---

## 9. Complete File List

### Configuration & DI
| File | Change |
|------|--------|
| `etc/config.xml` | Added `open_refund_capture_flag` default |
| `etc/adminhtml/system.xml` | Added capture flag field in Credit/Debit Cards group |
| `etc/db_schema.xml` | Added `fiserv_open_refund` table |
| `etc/acl.xml` | Added `open_refunds` + `open_refunds_manage` resources |
| `etc/adminhtml/menu.xml` | Added Sales → Open Refunds item |
| `etc/di.xml` | Added 2 repository/search-result preferences |
| `Gateway/Config/CommerceHub/Config.php` | Added `KEY_OPEN_REFUND_CAPTURE_FLAG` + getter |

### PHP — API / Model Layer
| File | Purpose |
|------|---------|
| `Api/Data/OpenRefund/OpenRefundInterface.php` | Entity interface — KEY_* constants + method signatures |
| `Api/Data/OpenRefund/OpenRefundSearchResultInterface.php` | Search result interface |
| `Api/OpenRefund/OpenRefundRepositoryInterface.php` | Repository contract |
| `Model/OpenRefund.php` | Entity model — one-liner getters/setters, status constants |
| `Model/OpenRefund/OpenRefundSearchResult.php` | Search result (extends SearchResults) |
| `Model/ResourceModel/OpenRefund.php` | DB resource model |
| `Model/ResourceModel/OpenRefund/Collection.php` | Grid-compatible collection |
| `Model/OpenRefundRepository.php` | CRUD repository |
| `Model/Source/OpenRefund/Status.php` | Status select options for grid filter |
| `Model/Service/OpenRefund/OpenRefundService.php` | Core business logic |

### PHP — UI Data Providers
| File | Purpose |
|------|---------|
| `Model/Ui/OpenRefund/ListingDataProvider.php` | Grid data; overrides getData(); formats amount |
| `Model/Ui/OpenRefund/FormDataProvider.php` | Form data (empty on create) |
| `Model/Ui/OpenRefund/CustomerOptions.php` | All customers as select options |
| `Model/Ui/OpenRefund/CustomerTokenOptions.php` | Token select placeholder |

### PHP — Controllers
| File | Route | ACL | Response |
|------|-------|-----|----------|
| `Controller/Adminhtml/OpenRefund/Index.php` | `fiserv/openRefund/index` | `open_refunds` | Full page |
| `Controller/Adminhtml/OpenRefund/Edit.php` | `fiserv/openRefund/edit` | `open_refunds_manage` | Full page |
| `Controller/Adminhtml/OpenRefund/Save.php` | `fiserv/openRefund/save` (POST) | `open_refunds_manage` | **JSON** |
| `Controller/Adminhtml/OpenRefund/GetTokens.php` | `fiserv/openRefund/getTokens` (GET) | `open_refunds_manage` | JSON |
| `Controller/Adminhtml/OpenRefund/TokenizeCard.php` | `fiserv/openRefund/tokenizeCard` (POST) | `open_refunds_manage` | JSON |

### PHP — Blocks
| File | Purpose |
|------|---------|
| `Block/Adminhtml/OpenRefund/CreateButton.php` | "Create New Open Refund" grid button |
| `Block/Adminhtml/OpenRefund/FormInit.php` | Injects CH config JSON + invalidFields into edit page |

### View — Admin
| File | Purpose |
|------|---------|
| `view/adminhtml/layout/fiserv_openrefund_index.xml` | Grid page layout |
| `view/adminhtml/layout/fiserv_openrefund_edit.xml` | Form page layout |
| `view/adminhtml/ui_component/fiserv_open_refund_listing.xml` | Grid UI definition |
| `view/adminhtml/ui_component/fiserv_open_refund_form.xml` | Form UI definition |
| `view/adminhtml/templates/open-refund/form-init.phtml` | Banner div + JS initializer script |
| `view/adminhtml/templates/open-refund/hosted-fields.phtml` | Hosted field iframes (sdc-* structure) |
| `view/adminhtml/templates/open-refund/index-banner.phtml` | sessionStorage flash message on index |
| `view/adminhtml/templates/open-refund/save-button.phtml` | "Submit Refund" toolbar button |
| `view/adminhtml/web/js/open-refund/form.js` | All form JS |
| `view/adminhtml/web/js/open-refund/amount-validator.js` | Standalone validate-currency-amount rule |
| `view/base/web/css/ch-form-admin.css` | Hosted field CSS (shared; not created by this feature) |

---

## 10. Configuration Reference

| Config path | Default | PHP accessor |
|-------------|---------|-------------|
| `payment/fiserv_commercehub/open_refund_capture_flag` | `1` (Yes) | `Config::isOpenRefundCaptureFlag()` |
| `payment/fiserv_commercehub/open_refund_transaction_limit` | unset | `Config::getValue('open_refund_transaction_limit')` |

---

## 11. Design Decisions & Why

### Save.php returns JSON, not a redirect
1. On failure the form must stay open — re-entering all data is a bad UX.
2. Hosted field iframes lose their state on a full page reload.
3. Flash messages use `sessionStorage` to bridge the redirect cleanly.

### TokenizeCard controller (two-step new card flow)
CH's `/payments/v1/refunds` rejects `PaymentSession` as a source. An extra server-side
call to `payments-vas/v1/tokens` exchanges the session for a `PaymentToken`. This mirrors
the admin order-create flow's tokenization pattern.

### Hosted field callbacks must be real implementations
`chAdapter.initialize()` receives callbacks for brand change, field validity, and field
focus. Passing no-ops means the SDK fires events that are silently ignored — no green/red
borders, no brand icon updates, no focused-field highlight. All three callbacks must be
implemented for the form to behave like the order-create form.

### ListingDataProvider overrides getData()
Magento's standard `DataProvider::getData()` calls `SearchCriteria::getFilterGroups()`
which returns `null` on initial page load, causing a `foreach on null` PHP fatal error. The
override bypasses this by querying the collection directly.

### OpenRefund.php must keep explicit interface methods
`AbstractModel::__call` provides dynamic `getData/setData` at runtime but cannot satisfy
PHP's compile-time interface contract. Removing the methods causes a fatal error. The
methods are written as one-liners to stay concise while meeting the requirement.

### No custom PHP class for the Submit button
A class that only sets `$_template` adds zero logic. `Magento\Backend\Block\Template`
handles this natively. `Block/Adminhtml/OpenRefund/Edit/SaveButton.php` was deleted.

### admin_user_id comes from AdminSession only
The admin user ID is always sourced from `AdminSession::getUser()` in `Save.php`. It is
never read from the POST body — prevents privilege escalation via form tampering.

### No order linkage by design
Open Refunds are standalone transactions. Linking them back to Magento orders (via
`order_increment_id`) or prior CH transactions (via `reference_transaction_id`) creates
a misleading audit trail — the refund has no relationship to those entities from
CommerceHub's perspective. Both fields were removed after initial build. See [§15](#15-changelog--post-initial-guide-changes).

---

## 12. Debugging Guide

### HTTP 500 on page load
**Most likely:** Interface contract violation in `Model/OpenRefund.php`. If any method
declared in `OpenRefundInterface` is missing from the class, PHP throws a fatal at class
instantiation. Check `var/log/exception.log` for *"contains N abstract methods"*.

### Token dropdown doesn't repopulate after selecting customer
- Open Network tab → check the `getTokens` XHR.
- **403** — `_isAllowed()` failing on `GetTokens.php`.
- **500** — PHP error, check `var/log/exception.log`.
- **`tokens: []`** — customer has no active `fiserv_commercehub` tokens in any store.
  Check `vault_payment_token`: `is_active=1`, `payment_method_code='fiserv_commercehub'`,
  `expires_at` in the future.

### Hosted fields don't appear / iframes fail
- Check browser console for errors from `chAdapter.instantiateIframe`.
- Verify `form-init.phtml` is rendering (right-click → View Source, search `text/x-magento-init`).
- Verify `paymentConfig.formConfig` is present in that JSON.
- Check `var/log/fiserv_payments.log` for any errors from `FormInit`.
- `#open-refund-hosted-error` will be visible on mount failure.

### Eye icon (unmask) does nothing
- `bindMaskToggle()` is only called after successful `instantiateIframe`. If mounting failed,
  the handlers were never registered.
- Check console for `[OpenRefund]` errors.
- Verify `chAdapter.unmask` and `chAdapter.mask` exist in the loaded SDK version.

### Field borders don't turn green/red / error messages are blank
- `onFieldValidity` requires `paymentConfig.invalidFields` to be non-empty.
- Verify `FormInit::getPaymentConfigJson()` includes `ConfigProvider::INVALID_FIELDS_KEY`.
- Check that `ConfigProvider::getInvalidFieldMessages(KEY_SDC_ADMIN, $storeId)` returns
  data for the configured admin form ID.

### "Card tokenization failed"
- Check `var/log/fiserv_payments.log` for `[OpenRefund] Tokenization failed: ...`.
- `TokenizeCard.php` calls CH `payments-vas/v1/tokens` — confirm credentials have
  tokenization endpoint access in the current environment (sandbox vs production).
- Verify `session_id` is being passed (check the `tokenizeCard` XHR request payload).

### "Refund was declined by the payment gateway"
- Check log for `Open Refund Response (HTTP NNN):` (debug) and
  `Open Refund CH returned non-CAPTURED state:` (error).
- Common causes: expired vault token, invalid merchant credentials, amount over limit.

### Amount validation rejected on the form
- Must match `/^\d+\.\d{1,2}$/` — at least one decimal place, max two.
- `10` fails, `10.99` passes, `10.999` fails.

### Grid shows 500 ("foreach on null")
- The `ListingDataProvider.getData()` override fixes this. If it recurs after a Magento
  upgrade, check whether the parent `DataProvider::getData()` signature changed.

### Success banner missing on index page after submit
- Verify `sessionStorage.setItem(...)` runs in `doAjaxSave` success branch.
- Verify `index-banner.phtml` is included in `fiserv_openrefund_index.xml`.
- Some browser privacy modes block `sessionStorage` — test in a normal window.

---

## 13. QA Test Cases

| # | Scenario | Expected |
|---|----------|---------|
| 1 | Navigate to Admin → Sales → Open Refunds | Grid renders; "Create New Open Refund" button visible |
| 2 | Admin without `open_refunds` ACL accesses index | 403 / redirected |
| 3 | Admin without `open_refunds_manage` accesses create form | 403 / redirected |
| 4 | Select a customer with active vault tokens | Token dropdown repopulates with their cards |
| 5 | Select a customer with no active tokens | Token dropdown shows "No saved cards found" |
| 6 | Submit with amount = 0 | Client-side validation error; no API call |
| 7 | Submit integer amount (`10`) | validate-currency-amount rejects; needs `10.00` |
| 8 | Submit 3 decimal places (`10.999`) | validate-currency-amount rejects |
| 9 | Amount exceeds `open_refund_transaction_limit` | Server error banner; record=failed; no CH call |
| 10 | Vault path — valid token — good credentials | record=success; transactionId stored; success banner on index |
| 11 | Vault path — expired/inactive token | LocalizedException; record=failed; error banner; form stays open |
| 12 | Switch to "Enter New Card" | `#hosted-fields-wrap` appears; iframes mount in `sdc-*` containers |
| 13 | Type card number | Brand icon updates dynamically |
| 14 | Valid card number entered, then blur | Field border turns green; no error message |
| 15 | Invalid card number entered, then blur | Field border turns red; error message shows below |
| 16 | Click eye icon on card number | Card digits revealed; icon swaps to re-mask eye |
| 17 | Click re-mask icon | Card digits masked; icon swaps back to unmask eye |
| 18 | New card — valid card — good credentials | Session tokenized via TokenizeCard; record=success |
| 19 | New card — TokenizeCard returns error | Error banner; hosted fields re-armed for retry |
| 20 | New card — SDK unavailable | `#open-refund-hosted-error` shown; form cannot submit |
| 21 | CH returns non-CAPTURED state | record=failed; error banner; form stays open |
| 22 | Submit and confirm no order history is written | No order timeline entries created anywhere |
| 23 | Credit memo on an existing order | Routes through `FiservCommerceHubRefundCommand` unchanged |
| 24 | Grid after successful refund | Amount formatted `$##.##`; status=success; transactionId visible |
| 25 | Double-click "Submit Refund" (new card) | `tokenising` lock prevents second submission |
| 26 | CH gateway unreachable | record=failed; "Communication with the payment gateway failed." |

---

## 14. What Is Out of Scope

- **Credit memo flow** — `FiservCommerceHubRefundCommand` is untouched.
- **Multi-currency** — hardcoded USD; no currency selector planned.
- **Edit/delete records** — grid is read-only; create only.
- **Real-time status polling** — grid shows DB-persisted status only.
- **Order linkage** — open refunds have no relationship to Magento orders by design.
- **Email notifications** — post-MVP.
- **Reconciliation sync** — separate CH reporting API work.
- **Storefront access** — admin panel only.

---

## 15. Changelog — Post-Initial-Guide Changes

This section records deliberate changes made after the first version of this guide was
written (after commit `0004bd2`).

---

### Commit `2d4a077` — Remove order history comment traceability

**Why:** Open Refunds are standalone, unlinked transactions by design. Writing a comment
to a Magento order's history timeline created a false association — the refund has no
relationship to that order from CommerceHub's perspective and could mislead support staff
into thinking it was a linked credit memo.

**Files changed:**

`Model/Service/OpenRefund/OpenRefundService.php`:
- Removed `use Magento\Sales\Api\OrderRepositoryInterface`
- Removed `private readonly OrderRepositoryInterface $orderRepository` from constructor
- Removed `$this->addOrderHistoryComment(...)` call from `submit()` success path
- Removed the entire `addOrderHistoryComment()` private method (~40 lines)
- Removed `$orderId` variable from `submit()` — no order context is needed
- Removed `"Order ID: $orderId"` third argument from all `logInfo/logDebug/logError/logCritical` calls
- Simplified `buildRefundRequest()` signature — removed `string $orderId` parameter

---

### Commit `59a7e5b` — Remove Reference Transaction ID and Associated Order # fields

**Why:**
- `order_increment_id` became orphaned input after the order history comment was removed —
  nothing consumed it.
- `reference_transaction_id` is conceptually wrong for an open refund. If you have a prior
  transaction to reference, use a linked credit memo. Keeping this field adds UI noise that
  implies a linkage that doesn't exist.

**Files changed:**

`etc/db_schema.xml`:
- Removed `reference_transaction_id` varchar(255) column from `fiserv_open_refund`
- Removed `order_increment_id` varchar(50) column from `fiserv_open_refund`
- Removed `IDX_FISERV_OPEN_REFUND_ORDER_INCREMENT_ID` index

> **Migration note:** Deploy with `bin/magento setup:db-schema:upgrade` — Magento
> declarative schema drops the columns automatically.

`Api/Data/OpenRefund/OpenRefundInterface.php`:
- Removed `KEY_REFERENCE_TRANSACTION_ID` and `KEY_ORDER_INCREMENT_ID` constants
- Removed `getReferenceTransactionId()`, `setReferenceTransactionId()`,
  `getOrderIncrementId()`, `setOrderIncrementId()` method signatures

`Model/OpenRefund.php`:
- Removed the four corresponding one-liner getter/setter implementations

`Controller/Adminhtml/OpenRefund/Save.php`:
- Removed `$openRefund->setReferenceTransactionId(...)` call
- Removed `$openRefund->setOrderIncrementId(...)` call

`Model/Service/OpenRefund/OpenRefundService.php`:
- Removed `$referenceTransactionId` block from `buildRefundRequest()` that previously
  called `$transactionDetails->setMerchantOrderId($referenceTransactionId)`

`view/adminhtml/ui_component/fiserv_open_refund_form.xml`:
- Removed `reference_transaction_id` field block from Refund Details fieldset
- Removed `order_increment_id` field block from Refund Details fieldset

