# Paze Integration Update - COMPLETED

## Objective
Update the Paze payment method implementation based on the documentation to properly integrate:
1. Step 4: Initialize the Paze component
2. Step 5: Launch the Paze UI (selectPaymentMethod)
3. Step 6: Submit Paze payment method (submit)
4. Connect to Charges API like Apple Pay

## Files Updated

### 1. `view/frontend/web/js/view/payment/method-renderer/commercehub-paze.js`
Complete rewrite following Apple Pay pattern:
- [x] Uses `create-commercehub-enriched-session` for credentials (like Apple Pay)
- [x] Implements `loadPazeForm()` that initializes Paze component (Step 4)
- [x] Creates dynamic "Pay with Paze" button
- [x] Implements `selectPazePaymentMethod()` (Step 5)
- [x] Handles payment success with `onPazeSuccess()` callback
- [x] Implements `placeOrderCallback()` for Charges API integration
- [x] `getData()` returns payment data for backend processing
- [x] `placeOrder()` redirects to success page
- [x] Checkout agreements block in template

### 2. `view/frontend/web/template/payment/commercehub/paze-form.html`
- [x] Added checkout-agreements-block for terms acceptance
- [x] Button container for dynamic Paze button

### 3. `view/base/web/js/ch-adapter.js`
- [x] Updated `loadPazeComponent()` with proper options handling
- [x] Added `cspNonce` parameter support for security

## Implementation Details

### Paze Flow (Same as Apple Pay/Charges API):
1. User selects Paze payment method → `initializePaze()` called
2. `loadPazeForm()` initializes SDK and credentials
3. Paze component initialized with `displayName` and `cspNonce`
4. "Pay with Paze" button rendered
5. User clicks button → `selectPazePaymentMethod()` called
6. Paze UI launches (iFrame + popup for authentication)
7. User authenticates and selects card
8. On success → `onPazeSuccess()` stores payment data
9. User clicks "Place Order" → `placeOrderCallback()` calls Magento's place-order action
10. Backend processes payment via Charges API
11. Redirect to success page

### Payment Data Sent to Backend:
```javascript
{
    method: 'fiserv_paze',
    additional_data: {
        payment_source: 'paze',
        paze_order_id: '<order-id>',
        payment_session: '<session-id>',
        paze_payment_token: '<payment-token>'
    }
}
```

