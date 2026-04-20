# Open Refund — Implementation Plan

## Overview

An **Open Refund** (also called a "standalone" or "unlinked" refund) is a refund sent directly
to the CommerceHub API at `POST /payments/v1/refunds` **without** a `referenceTransactionDetails`
object. Instead, the request carries a full payment `source` (either a stored vault token or a
new card entered via hosted fields).

This feature is **exclusively** accessible through a new admin panel at
**Admin → Sales → Open Refunds**. It is **not** tied to any existing order or credit memo flow.
Existing credit memo behaviour is left entirely unchanged.

### Key API Difference vs. a Referenced Refund

| Field | Referenced Refund (existing) | Open Refund (this feature) |
|---|---|---|
| `referenceTransactionDetails` | ✅ Required | ❌ Omitted |
| `source` | ❌ Omitted | ✅ Required (`PaymentToken` or `PaymentSession`) |
| `amount` | ✅ Required | ✅ Required |
| `transactionDetails` | ✅ Required | ✅ Required |
| `merchantDetails` | ✅ Required | ✅ Required |

---

## Request Flow

```
Admin navigates to Sales → Open Refunds
            ↓
    [Grid] fiserv/openRefund/index
            ↓
    Admin clicks "Create New Open Refund"
            ↓
    [Form] fiserv/openRefund/edit
     - Select customer
     - Customer change → AJAX → GetTokens → populates vault_token_hash dropdown
     - Select saved card  OR  switch to "Enter New Card" → hosted field iframes appear
     - Enter amount, currency, optional reference ID, optional notes
     - Click "Submit Refund"
            ↓
    [POST] fiserv/openRefund/save  →  Controller/Adminhtml/OpenRefund/Save.php
            ↓
    Model/Service/OpenRefundService.php
     - Enforce transaction limit
     - Resolve source: vault token OR hosted-fields session token
     - Build RefundRequest (NO referenceTransactionDetails)
     - Save record as status=pending
     - POST /payments/v1/refunds → CommerceHub
     - Update record: status=success (transaction_id, masked_card) OR status=failed
            ↓
    Redirect to grid with success / error message
```

---

## Step-by-Step Implementation

---

### Step 1 — Admin Config ✅ Done

**Goal:** Add merchant-configurable settings that control open refund behaviour.
These settings live under **Stores → Configuration → Payment Methods → Fiserv CommerceHub → Credit/Debit Cards**.

**`etc/config.xml`** — add defaults under `<fiserv_commercehub>`:

```xml
<open_refund_capture_flag>1</open_refund_capture_flag>
```

**`etc/adminhtml/system.xml`** — add one field inside the `credit_debit_cards` group (sortOrder 10):

```xml
<field id="open_refund_capture_flag" translate="label comment" type="select"
       sortOrder="10" showInDefault="1" showInWebsite="1" showInStore="0">
    <label>Open Refund Capture Flag</label>
    <comment>Yes = captureFlag=true (immediate settlement). No = authorisation only.</comment>
    <source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
    <config_path>payment/fiserv_commercehub/open_refund_capture_flag</config_path>
</field>
```

**`Gateway/Config/CommerceHub/Config.php`** — add constant and getter:

```php
const KEY_OPEN_REFUND_CAPTURE_FLAG = 'open_refund_capture_flag';

public function isOpenRefundCaptureFlag($storeId = null): bool
{
    return (bool) $this->getValue(self::KEY_OPEN_REFUND_CAPTURE_FLAG, $storeId);
}
```

**QA:** Navigate to Stores → Config → Fiserv CommerceHub → Credit/Debit Cards. Confirm the field appears and saves correctly.

---

### Step 2 — Database Table ✅ Done

**Goal:** Persist every standalone open refund — including its status, transaction result, and
customer snapshot — in a dedicated table.

**`etc/db_schema.xml`** — add table `fiserv_open_refund`:

| Column | Type | Notes |
|---|---|---|
| `entity_id` | int unsigned, auto-increment | Primary key |
| `amount` | decimal(12,2) | Refund amount |
| `currency_code` | varchar(10) | e.g. `USD` |
| `customer_id` | int unsigned, nullable | FK → `customer_entity` (SET NULL on delete) |
| `customer_email` | varchar(255), nullable | Snapshotted at submission time |
| `customer_name` | varchar(255), nullable | Snapshotted at submission time |
| `admin_user_id` | int unsigned, nullable | Populated server-side from admin session |
| `status` | varchar(20) | `pending` / `success` / `failed` |
| `transaction_id` | varchar(255), nullable | CommerceHub transaction ID |
| `masked_card` | varchar(20), nullable | Last 4 digits from CH response |
| `reference_transaction_id` | varchar(255), nullable | Optional audit trail link |
| `notes` | text, nullable | Free-text admin notes |
| `created_at` | timestamp | Default `CURRENT_TIMESTAMP` |

**QA:** Run `bin/magento setup:upgrade`. Confirm table exists with all columns via `DESCRIBE fiserv_open_refund;`.

---

### Step 3 — Model / Repository / API Layer ✅ Done

**Goal:** A full Magento 2 model stack so the record can be created, queried, and displayed.

#### Files to create

| File | Purpose |
|---|---|
| `Api/Data/OpenRefund/OpenRefundInterface.php` | Entity interface — getters/setters for every column |
| `Api/Data/OpenRefund/OpenRefundSearchResultInterface.php` | Search result interface |
| `Api/OpenRefund/OpenRefundRepositoryInterface.php` | `get()`, `getList()`, `save()`, `delete()` |
| `Model/OpenRefund.php` | `AbstractModel` with `STATUS_*` + `KEY_*` constants |
| `Model/OpenRefund/OpenRefundSearchResult.php` | `AbstractSimpleObject` implementing search result |
| `Model/ResourceModel/OpenRefund.php` | `AbstractDb` mapped to `fiserv_open_refund` |
| `Model/ResourceModel/OpenRefund/Collection.php` | Collection class |
| `Model/OpenRefundRepository.php` | Full repository implementation |
| `Model/Source/OpenRefund/Status.php` | `OptionSourceInterface` — pending / success / failed |

#### `etc/di.xml` preferences to add

```xml
<preference for="Fiserv\Payments\Api\OpenRefund\OpenRefundRepositoryInterface"
            type="Fiserv\Payments\Model\OpenRefundRepository"/>
<preference for="Fiserv\Payments\Api\Data\OpenRefund\OpenRefundSearchResultInterface"
            type="Fiserv\Payments\Model\OpenRefund\OpenRefundSearchResult"/>
```

**QA:** Run `bin/magento setup:di:compile`. No errors.

---

### Step 4 — ACL Resources ✅ Done


**`etc/acl.xml`** — add under the Fiserv resource tree:

```xml
<resource id="Fiserv_Payments::open_refunds" title="Open Refunds" sortOrder="30">
    <resource id="Fiserv_Payments::open_refunds_manage"
              title="Create / Edit Open Refunds" sortOrder="10"/>
</resource>
```

Two levels:
- `open_refunds` — view the grid
- `open_refunds_manage` — create refunds, access the form, call GetTokens

**QA:** Admin → System → Permissions → User Roles → [Role] → Role Resources. Confirm both nodes are visible under Fiserv.

---

### Step 5 — Admin Menu Item ✅ Done

**Goal:** Surface the feature at **Admin → Sales → Open Refunds**.

**`etc/adminhtml/menu.xml`**:

```xml
<add id="Fiserv_Payments::open_refunds"
     title="Open Refunds"
     module="Fiserv_Payments"
     sortOrder="100"
     parent="Magento_Sales::sales"
     action="fiserv/openRefund/index"
     resource="Fiserv_Payments::open_refunds"/>
```

**QA:** `bin/magento cache:flush`. Open Admin → Sales. Confirm "Open Refunds" link appears.

---

### Step 6 — Admin Controllers ✅ Done

**Goal:** Four controllers handle every request the feature makes.

#### `Controller/Adminhtml/OpenRefund/Index.php`
- Route: `fiserv/openRefund/index`
- ACL: `Fiserv_Payments::open_refunds`
- Returns a full-page result that loads the grid layout.

#### `Controller/Adminhtml/OpenRefund/Edit.php`
- Route: `fiserv/openRefund/edit`
- ACL: `Fiserv_Payments::open_refunds_manage`
- Returns a full-page result that loads the form layout.

#### `Controller/Adminhtml/OpenRefund/Save.php`
- Route: `fiserv/openRefund/save` (POST only — implement `HttpPostActionInterface`)
- ACL: `Fiserv_Payments::open_refunds_manage`
- Logic:
  1. Validate amount > 0.
  2. Load customer name + email snapshot via `CustomerRepositoryInterface`.
  3. Build `OpenRefund` model — set all fields from POST.
  4. Set `admin_user_id` from `Magento\Backend\Model\Auth\Session` (never from POST).
  5. Call `OpenRefundService::submit($openRefund, $postData)`.
  6. Success → `addSuccessMessage` + redirect to index.
  7. `LocalizedException` → `addErrorMessage` + redirect back to edit.

#### `Controller/Adminhtml/OpenRefund/GetTokens.php`
- Route: `fiserv/openRefund/getTokens` (GET only — implement `HttpGetActionInterface`)
- ACL: `Fiserv_Payments::open_refunds_manage`
- Logic:
  1. Read `customer_id` from request params.
  2. Query `PaymentTokenRepositoryInterface`: `customer_id` + `is_active=1` + `payment_method_code=fiserv_commercehub`.
  3. Build label from token details JSON: `"VISA ending 4242 (exp 12/26)"`.
  4. Return JSON: `{ "tokens": [{ "value": "<public_hash>", "label": "..." }] }`.

**QA:** Navigate to all four routes directly. Confirm ACL blocks unauthorised access.

---

### Step 7 — Admin UI — Grid ✅ Done

**Goal:** A dashboard that lists all open refund records with filtering, sorting, and export.

#### Layout — `view/adminhtml/layout/fiserv_openrefund_index.xml`

```xml
<page>
  <body>
    <referenceContainer name="page.main.actions">
      <!-- "Create New Open Refund" button block -->
      <block class="Fiserv\Payments\Block\Adminhtml\OpenRefund\CreateButton"
             name="open_refund_add_button"/>
    </referenceContainer>
    <referenceContainer name="content">
      <uiComponent name="fiserv_open_refund_listing"/>
    </referenceContainer>
  </body>
</page>
```

> The "Create" button must be declared as a layout block (not inside the UI component XML).
> Magento's UI component schema does not support `<url>` as a toolbar button child.

#### Block — `Block/Adminhtml/OpenRefund/CreateButton.php`
Extends `Magento\Backend\Block\Widget\Button`. Sets label = "Create New Open Refund",
`onclick` = `setLocation(url('fiserv/openRefund/edit'))`, class = `primary`.

#### UI Component — `view/adminhtml/ui_component/fiserv_open_refund_listing.xml`

Data source: `Fiserv\Payments\Model\Ui\OpenRefund\ListingDataProvider`

Columns to include:

| Column | Type | Filter |
|---|---|---|
| `entity_id` | text | — |
| `created_at` | date | dateRange |
| `customer_name` | text | text |
| `customer_email` | text | text |
| `amount` | text | text |
| `currency_code` | text | text |
| `masked_card` | text | — |
| `status` | select | `Model/Source/OpenRefund/Status.php` |
| `transaction_id` | text | text |
| `reference_transaction_id` | text | text |

Toolbar: bookmarks, column chooser, filters, paging, export (CSV + XML).

#### Data Provider — `Model/Ui/OpenRefund/ListingDataProvider.php`
Extends `Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider`.
Uses `Model/ResourceModel/OpenRefund/Collection.php` ordered `created_at DESC`.

**QA:** Navigate to Sales → Open Refunds. Grid renders with correct columns, filter, and paging.

---

### Step 8 — Admin UI — Create Form ✅ Done

**Goal:** A two-fieldset form for submitting a new open refund.

#### Layout — `view/adminhtml/layout/fiserv_openrefund_edit.xml`

```xml
<page>
  <body>
    <referenceContainer name="content">
      <uiComponent name="fiserv_open_refund_form"/>
    </referenceContainer>
  </body>
</page>
```

#### UI Component — `view/adminhtml/ui_component/fiserv_open_refund_form.xml`

Submit URL: `fiserv/openRefund/save`
Data provider: `Fiserv\Payments\Model\Ui\OpenRefund\FormDataProvider`

**Fieldset 1 — Refund Details**

| Field | Element | Notes |
|---|---|---|
| `customer_id` | select | Options from `CustomerOptions` (name + email list) |
| `amount` | input (number) | Required, `validate-number`, `validate-greater-than-zero` |
| `currency_code` | select | USD, CAD |
| `reference_transaction_id` | input (text) | Optional — audit trail link |
| `notes` | textarea | Optional |

**Fieldset 2 — Payment Source**

| Field | Element | Notes |
|---|---|---|
| `payment_source_type` | select | `vault` = "Use Saved Card", `new_card` = "Enter New Card" |
| `vault_token_hash` | select | Options from `CustomerTokenOptions` (empty on create, reloaded via AJAX) |
| hosted fields block | htmlContent | Rendered from `hosted-fields.phtml` |

`vault_token_hash` should only be visible when `payment_source_type = vault`.
Use a KnockoutJS `<link name="visible">` binding tied to the provider data.

#### Data Providers

**`Model/Ui/OpenRefund/FormDataProvider.php`** — extends `AbstractDataProvider`.
Returns empty `[]` on create. Pre-fills model data on edit (loaded from collection by `entity_id`).

**`Model/Ui/OpenRefund/CustomerOptions.php`** — implements `OptionSourceInterface`.
Queries all customers via `CustomerRepositoryInterface`. Label = `"John Smith <john@example.com>"`.

**`Model/Ui/OpenRefund/CustomerTokenOptions.php`** — implements `OptionSourceInterface`.
Returns `[['value' => '', 'label' => '-- Select a customer first --']]` on initial page load.
The actual options are loaded client-side via AJAX (Step 9).

**QA:** Navigate to the create form. Both fieldsets render. Customer dropdown shows all customers.
Switching Payment Method toggles the vault select / hosted fields correctly.

---

### Step 9 — AJAX Customer → Token Reload ✅ Done

**Goal:** When the admin selects a customer, the `vault_token_hash` dropdown immediately
repopulates with that customer's active vault tokens — no page reload required.

#### Backend (already covered in Step 6)
`Controller/Adminhtml/OpenRefund/GetTokens.php` returns:
```json
{ "tokens": [{ "value": "abc123publicHash", "label": "VISA ending 4242 (exp 12/26)" }] }
```

#### Template — `view/adminhtml/templates/open-refund/hosted-fields.phtml`

This template is rendered inside the `htmlContent` block in the form UI component.
It outputs two things:
1. The `#hosted-fields-wrap` div containing iframe containers for card number, name, expiry, CVV.
2. A `<script type="text/x-magento-init">` block that initialises `form.js` with `getTokensUrl`.

```php
<?php $getTokensUrl = $block->getUrl('fiserv/openRefund/getTokens'); ?>
<div id="open-refund-hosted-fields" data-get-tokens-url="<?= $block->escapeHtmlAttr($getTokensUrl) ?>">
    <input type="hidden" name="hosted_field_token" id="hosted_field_token" value=""/>
    <div id="hosted-fields-wrap" style="display:none;">
        <!-- iframe containers for card number, name on card, exp month, exp year, cvv -->
    </div>
</div>
<script type="text/x-magento-init">
{
    "#open-refund-hosted-fields": {
        "Fiserv_Payments/js/open-refund/form": {
            "getTokensUrl": "<?= $block->escapeJs($getTokensUrl) ?>"
        }
    }
}
</script>
```

#### JavaScript — `view/adminhtml/web/js/open-refund/form.js`

RequireJS module. Exported as a plain function (called by `x-magento-init` with `config`).

**Responsibilities:**

1. **Customer → token AJAX reload**
   - Listen on `$(document).on('change', 'select[name*="customer_id"]', ...)`.
     > ⚠️ Magento renders the field name as `data[customer_id]` (due to the form's `dataScope="data"`
     > prefix), so the selector must use `[name*="customer_id"]`, not `[name="customer_id"]`.
   - On change: `fetch(getTokensUrl + '?customer_id=' + id, { credentials: 'include' })`
   - On success:
     - `rebuildDomOptions(tokens)` — directly rebuild `<option>` elements in
       `select[name*="vault_token_hash"]` (same name-prefix caveat applies).
     - `registry.async(TOKEN_COMP)(fn)` — call `tokenComp.setOptions(koOptions)` to keep
       the KnockoutJS observable in sync so the form submission picks up the value.
   - UI component registry path:
     `fiserv_open_refund_form.fiserv_open_refund_form.payment_source.vault_token_hash`

2. **Payment source type toggle**
   - `registry.async(SOURCE_TYPE_COMP)` → subscribe to `sourceComp.value`.
   - `vault` selected → show `vault_token_hash` select (`tokenComp.visible(true)`), hide `#hosted-fields-wrap`.
   - `new_card` selected → hide `vault_token_hash`, show `#hosted-fields-wrap`, call `mountHostedFields()`.
   - Source type component path:
     `fiserv_open_refund_form.fiserv_open_refund_form.payment_source.payment_source_type`

3. **Mount hosted field iframes**
   - `require(['Fiserv_Payments/js/commercehub-sdk'])` → `sdk.create(...)` for each field container.
   - Guard with a `fieldsReady` flag so iframes only mount once.
   - On SDK load failure: show `#open-refund-hosted-error` with a user-facing message.

4. **Intercept form submit for new card path**
   - `$(document).on('submit', 'form[data-ui-id]', fn)`.
   - If `payment_source_type !== 'new_card'` → do nothing (let form submit normally).
   - If `new_card` → `e.preventDefault()`, call `sdk.tokenize()`, inject session token into
     `#hosted_field_token`, then re-submit.

**QA:**
- Select a customer → token dropdown populates immediately.
- Switch to "Enter New Card" → hosted field iframes appear.
- Switch back to "Use Saved Card" → iframes hide, token dropdown reappears.
- Disable JS SDK URL → error message appears in the hosted fields area.

---

### Step 10 — Service Layer ✅ Done

**Goal:** Orchestrate the full submit flow — validation, source resolution, API call, persistence.

**`Model/Service/OpenRefundService.php`**

```
submit(OpenRefund $openRefund, array $formData): void

 1. Enforce transaction limit
    - Config::getOpenRefundTransactionLimit() → if > 0 and amount exceeds it → throw LocalizedException

 2. Resolve payment source
    - formData['payment_source_type'] === 'new_card'
        → buildSessionSource(): wrap formData['hosted_field_token'] as PaymentToken{sourceType=PaymentSession}
    - else (vault)
        → buildVaultSource():
            load VaultPaymentToken by public_hash + customer_id (is_active=1)
            strip $$TS$$=... suffix from gateway token
            read expMonth, expYear, tokenSource, nameOnCard from token details JSON
            build PaymentToken { tokenData, tokenSource, Card{expMonth, expYear, nameOnCard} }

 3. Build RefundRequest
    - Amount { total, currency }
    - MerchantDetails { merchantId, terminalId, merchantPartner }
    - TransactionDetails { captureFlag=Config::isOpenRefundCaptureFlag(), createToken=false }
      If referenceTransactionId set → TransactionDetails::setMerchantOrderId(referenceTransactionId)
    - RefundRequest { source, amount, transactionDetails, merchantDetails }
      ← NO referenceTransactionDetails

 4. Save record as status=pending (before API call)

 5. POST payload to /payments/v1/refunds via ChHttpAdapter

 6. Parse response
    - HTTP 2xx + transactionState=CAPTURED
        → status=success, save transactionId + maskedCard
    - Anything else
        → status=failed, save record, throw LocalizedException with CH error message
```

**QA:**
- Submit with amount exceeding limit → no API call, error shown.
- Submit vault path with valid token → CH called, record=success, transactionId stored.
- Submit vault path with bad credentials → record=failed, error shown to admin.
- Submit new_card path with session token → CH called, record=success.

---

### Step 11 — DI Wiring ✅ Done

**`etc/di.xml`** additions needed (beyond the two repository preferences in Step 3):

No new gateway virtual types, command pools, or builders are needed — the service layer
calls `ChHttpAdapter` directly, not through Magento's payment gateway command infrastructure.

The `fiserv/openRefund/save` route is never wired into the payment gateway. It is a plain
admin HTTP POST, handled entirely by `Save.php` → `OpenRefundService`.

Only additions required:
```xml
<!-- Repository and search result bindings (from Step 3) -->
<preference for="Fiserv\Payments\Api\OpenRefund\OpenRefundRepositoryInterface"
            type="Fiserv\Payments\Model\OpenRefundRepository"/>
<preference for="Fiserv\Payments\Api\Data\OpenRefund\OpenRefundSearchResultInterface"
            type="Fiserv\Payments\Model\OpenRefund\OpenRefundSearchResult"/>
```

**QA:** `bin/magento setup:di:compile` — zero errors.

---

## Complete File List

### Configuration
| File | Change |
|---|---|
| `etc/config.xml` | Add `open_refund_capture_flag` default ✅ |
| `etc/adminhtml/system.xml` | Add capture flag field under Credit/Debit Cards group ✅ |
| `etc/db_schema.xml` | Add `fiserv_open_refund` table ✅ |
| `etc/acl.xml` | Add `open_refunds` + `open_refunds_manage` resources ✅ |
| `etc/adminhtml/menu.xml` | Add Sales → Open Refunds menu item ✅ |
| `etc/di.xml` | Add 2 repository/search-result preferences ✅ |
| `Gateway/Config/CommerceHub/Config.php` | Add `KEY_OPEN_REFUND_CAPTURE_FLAG` constant + getter ✅ |

### PHP — Model Layer
| File | Purpose |
|---|---|
| `Api/Data/OpenRefund/OpenRefundInterface.php` | Entity interface ✅ |
| `Api/Data/OpenRefund/OpenRefundSearchResultInterface.php` | Search result interface ✅ |
| `Api/OpenRefund/OpenRefundRepositoryInterface.php` | Repository interface ✅ |
| `Model/OpenRefund.php` | Entity model ✅ |
| `Model/OpenRefund/OpenRefundSearchResult.php` | Search result implementation ✅ |
| `Model/ResourceModel/OpenRefund.php` | DB resource model ✅ |
| `Model/ResourceModel/OpenRefund/Collection.php` | Collection ✅ |
| `Model/OpenRefundRepository.php` | Repository implementation ✅ |
| `Model/Source/OpenRefund/Status.php` | Status option source ✅ |

### PHP — UI Data Providers
| File | Purpose |
|---|---|
| `Model/Ui/OpenRefund/ListingDataProvider.php` | Grid data source ✅ |
| `Model/Ui/OpenRefund/FormDataProvider.php` | Form data source (create + edit) ✅ |
| `Model/Ui/OpenRefund/CustomerOptions.php` | Customer select options ✅ |
| `Model/Ui/OpenRefund/CustomerTokenOptions.php` | Token select — initial empty state ✅ |

### PHP — Controllers
| File | Route | ACL |
|---|---|---|
| `Controller/Adminhtml/OpenRefund/Index.php` | `fiserv/openRefund/index` | `open_refunds` |
| `Controller/Adminhtml/OpenRefund/Edit.php` | `fiserv/openRefund/edit` | `open_refunds_manage` |
| `Controller/Adminhtml/OpenRefund/Save.php` | `fiserv/openRefund/save` (POST) | `open_refunds_manage` |
| `Controller/Adminhtml/OpenRefund/GetTokens.php` | `fiserv/openRefund/getTokens` (GET/AJAX) | `open_refunds_manage` |

### PHP — Service + Block
| File | Purpose |
|---|---|
| `Model/Service/OpenRefundService.php` | Orchestrates submit: validation → source → API → persist ✅ |
| `Block/Adminhtml/OpenRefund/CreateButton.php` | "Create New Open Refund" page header button ✅ |

### View — Adminhtml
| File | Purpose |
|---|---|
| `view/adminhtml/layout/fiserv_openrefund_index.xml` | Grid page layout ✅ |
| `view/adminhtml/layout/fiserv_openrefund_edit.xml` | Form page layout ✅ |
| `view/adminhtml/ui_component/fiserv_open_refund_listing.xml` | Grid UI definition ✅ |
| `view/adminhtml/ui_component/fiserv_open_refund_form.xml` | Form UI definition ✅ |
| `view/adminhtml/templates/open-refund/hosted-fields.phtml` | Hosted field containers + JS init ✅ |
| `view/adminhtml/web/js/open-refund/form.js` | Customer→token AJAX, source toggle, submit intercept ✅ |

---

## What Is Explicitly Out of Scope

- **Credit memo flow** — existing `FiservCommerceHubRefundCommand` and its path through the
  Magento payment gateway are **not touched**. `RefundStrategyCommand`, `OpenRefundSourceDataBuilder`,
  `OpenRefundComposite`, and `OpenRefundResponseValidator` are **not part of this feature**.
- **Real-time status polling** — grid shows the DB-persisted `status` only.
- **Reconciliation report sync** — tied to a separate CH reporting API integration.
- **Email notifications** — post-MVP.

---

## QA Test Cases

| # | Scenario | Expected |
|---|---|---|
| 1 | Navigate to Admin → Sales → Open Refunds | Grid renders, no errors, "Create" button present |
| 2 | Admin without `open_refunds` role accesses index | 403 / redirected |
| 3 | Admin without `open_refunds_manage` role accesses create form | 403 / redirected |
| 4 | Select a customer on the form | Token dropdown repopulates with their active vault tokens |
| 5 | Customer with no saved tokens | Token dropdown shows empty / no-cards message |
| 6 | Submit with amount = 0 | Validation error, no API call |
| 7 | Submit with amount exceeding transaction limit | Error message, no API call |
| 8 | Vault path — valid token — good credentials | Record=success, transactionId stored, grid shows success |
| 9 | Vault path — invalid token | Record=failed, error shown to admin |
| 10 | New card path — hosted fields | Iframes appear, card tokenized, session token POSTed, record=success |
| 11 | New card path — SDK unavailable | Error message: "Hosted fields could not be loaded. Please refresh or contact support." |
| 12 | CommerceHub returns non-CAPTURED status | Record=failed, LocalizedException shown to admin |
| 13 | Existing credit memo on a tokenized order | Routed through original `FiservCommerceHubRefundCommand` — **unchanged** |

