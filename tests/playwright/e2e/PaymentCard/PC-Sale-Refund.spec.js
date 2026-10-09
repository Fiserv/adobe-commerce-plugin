const { test, expect } = require('@playwright/test');

const storefrontUrl =
    'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

const adminUrl = `${storefrontUrl}/admin_gyx81dr`;

const adminUsername = 'tysonwin';
const adminPassword = 'password1';

const credentials = {
    merchantId: '100367000000275',
    terminalId: '10000001',
    apiKey: 's4C6s9ACBjvhspztLG7rXtTww7OugDXyA7HHSvSTmm1dSys2',
    apiSecret: 'oXUaAhFTiochiQYOpYAT0xNTAFrtDyDBCjnT4ooOKUGpWOlsFw1UdtCA6rzYyFvK'
};

const customerEmail = 'jean@pierre.com';
const customerPassword = 'Password1';

/*
 * Hosted field helpers for the Fiserv CommerceHub SDC iframes.
 */
async function getHostedFrame(page, containerSelector) {
    const container = page.locator(containerSelector);

    await expect(container).toBeVisible({
        timeout: 60000
    });

    const iframe = container.locator('iframe');

    await expect(iframe).toBeAttached({
        timeout: 60000
    });

    await expect(iframe).toBeVisible({
        timeout: 60000
    });

    return iframe.contentFrame();
}

async function fillHostedField(page, containerSelector, value) {
    const frame = await getHostedFrame(
        page,
        containerSelector
    );

    const input = frame.locator('input').first();

    await expect(input).toBeVisible({
        timeout: 30000
    });

    await input.fill(value);
}

async function selectHostedOption(
    page,
    containerSelector,
    selectSelector,
    value
) {
    const frame = await getHostedFrame(
        page,
        containerSelector
    );

    const select = frame.locator(selectSelector);

    await expect(select).toBeVisible({
        timeout: 30000
    });

    await select.selectOption(value);
}

test(
    `PC Sale Refund - MID ${credentials.merchantId}`,
    async ({ page }) => {
        test.setTimeout(420000);

        /* =============================================================
         * PART 1: ADMIN - UPDATE COMMERCEHUB CREDENTIALS
         * ============================================================= */

        /*
         * Admin login.
         */
        await page.goto(adminUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        const usernameInput = page.locator('#username');
        const passwordInput = page.locator('#login');

        await expect(usernameInput).toBeVisible({ timeout: 60000 });
        await expect(passwordInput).toBeVisible({ timeout: 60000 });

        await usernameInput.fill(adminUsername);
        await passwordInput.fill(adminPassword);

        const signInButton = page.getByText('Sign in', {
            exact: true
        });

        await expect(signInButton).toBeVisible({ timeout: 30000 });
        await signInButton.click();

        await expect(usernameInput).toBeHidden({ timeout: 60000 });

        /*
         * Wait for the Dashboard to fully load so Magento's admin menu
         * click handlers are attached before clicking Stores.
         */
        await page.waitForLoadState('load');
        await expect(
            page.getByRole('heading', { name: 'Dashboard', level: 1 })
        ).toBeVisible({ timeout: 60000 });

        /*
         * Open Stores > Configuration.
         */
        const storesMenuItem = page.locator('#menu-magento-backend-stores');
        const storesMenu = storesMenuItem.locator('> a');
        const configurationLink = storesMenuItem
            .locator('.submenu a')
            .filter({ hasText: /^\s*Configuration\s*$/ });

        await expect(storesMenu).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await configurationLink.isVisible())) {
                await storesMenu.click();
            }

            await expect(configurationLink).toBeVisible({ timeout: 3000 });
        }).toPass({
            timeout: 60000,
            intervals: [1000, 2000, 3000]
        });

        await configurationLink.click();

        await expect(page).toHaveURL(/system_config/, { timeout: 60000 });

        /*
         * Sales > Payment Methods.
         */
        const salesTab = page.getByRole('tab', {
            name: 'Sales '
        });

        await expect(salesTab).toBeVisible({ timeout: 60000 });
        await salesTab.click();

        const paymentMethodsLink = page.getByRole('link', {
            name: 'Payment Methods',
            exact: true
        });

        await expect(paymentMethodsLink).toBeVisible({ timeout: 60000 });
        await paymentMethodsLink.click();

        await expect(page).toHaveURL(
            /system_config\/edit\/section\/payment/,
            {
                timeout: 60000
            }
        );

        const commerceHubGateway = page.getByRole('link', {
            name: ' Commerce Hub Gateway'
        });

        await expect(commerceHubGateway).toBeVisible({ timeout: 60000 });
        await commerceHubGateway.click();

        /*
         * Expand Commerce Hub Gateway General Settings when collapsed.
         */
        const commerceHubSection = page.locator(
            '#payment_us_fiserv_payments_fiserv_commercehub-head'
        );

        await expect(commerceHubSection).toBeVisible({ timeout: 60000 });

        const merchantIdInput = page.locator(
            '#payment_us_fiserv_payments_fiserv_commercehub_merchant_id'
        );

        if (!(await merchantIdInput.isVisible())) {
            await commerceHubSection.click();
        }

        await expect(merchantIdInput).toBeVisible({ timeout: 30000 });

        const terminalIdInput = page.locator(
            '#payment_us_fiserv_payments_fiserv_commercehub_terminal_id'
        );
        const apiKeyInput = page.locator(
            '#payment_us_fiserv_payments_fiserv_commercehub_api_key'
        );
        const apiSecretInput = page.locator(
            '#payment_us_fiserv_payments_fiserv_commercehub_api_secret'
        );

        /*
         * Replace the current CommerceHub credentials.
         */
        await merchantIdInput.fill(credentials.merchantId);
        await terminalIdInput.fill(credentials.terminalId);
        await apiKeyInput.fill(credentials.apiKey);
        await apiSecretInput.fill(credentials.apiSecret);

        await expect(merchantIdInput).toHaveValue(credentials.merchantId);
        await expect(terminalIdInput).toHaveValue(credentials.terminalId);
        await expect(apiKeyInput).toHaveValue(credentials.apiKey);
        await expect(apiSecretInput).toHaveValue(credentials.apiSecret);

        /*
         * Credit/Debit Cards: set Payment Action to Sale (Auth & Capture).
         * Expand the section only if the Payment Action select is hidden,
         * so we never toggle an already-open section closed.
         */
        const creditDebitCardsSection = page.locator(
            '#payment_us_fiserv_payments_credit_debit_cards-head'
        );

        const paymentActionSelect = page.locator(
            '#payment_us_fiserv_payments_credit_debit_cards_payment_action'
        );

        await expect(creditDebitCardsSection).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await paymentActionSelect.isVisible())) {
                await creditDebitCardsSection.click();
            }

            await expect(paymentActionSelect).toBeVisible({ timeout: 3000 });
        }).toPass({
            timeout: 30000,
            intervals: [1000, 2000, 3000]
        });

        /*
         * If Magento's "Use system value" checkbox is ticked, the select is
         * disabled. Untick it so the select can be changed.
         */
        const paymentActionInherit = page.locator(
            '#payment_us_fiserv_payments_credit_debit_cards_payment_action_inherit'
        );

        if (
            (await paymentActionInherit.count()) &&
            (await paymentActionInherit.isChecked())
        ) {
            await paymentActionInherit.uncheck();
        }

        await expect(paymentActionSelect).toBeEnabled({ timeout: 30000 });

        await paymentActionSelect.selectOption('authorize_capture');

        await expect(paymentActionSelect).toHaveValue('authorize_capture');

        /*
         * Save Magento configuration.
         */
        const saveConfigButton = page.getByText('Save Config', {
            exact: true
        });

        await expect(saveConfigButton).toBeVisible({ timeout: 30000 });
        await saveConfigButton.click();

        const successMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(successMessage).toBeVisible({ timeout: 60000 });
        await expect(successMessage).toHaveText(
            'You saved the configuration.'
        );

        /*
         * Follow the Cache Management link from the System Message.
         */
        const cacheWarning = page.locator('.message-system-short');

        await expect(cacheWarning).toContainText(
            'One or more of the Cache Types are invalidated',
            { timeout: 60000 }
        );

        const cacheManagementLink = cacheWarning.getByRole('link', {
            name: 'Cache Management',
            exact: true
        });

        await expect(cacheManagementLink).toBeVisible({ timeout: 60000 });
        await cacheManagementLink.click();

        await expect(page).toHaveURL(/\/admin\/cache\/index\/key\//, {
            timeout: 60000
        });

        await page.waitForLoadState('load');

        /*
         * Check every Invalidated cache type row.
         */
        const flushMagentoButton = page.locator('#flush_magento');
        await expect(flushMagentoButton).toBeVisible({ timeout: 60000 });

        const invalidatedRows = page.locator('tr').filter({
            has: page.locator('span', { hasText: /^\s*Invalidated\s*$/ })
        });

        const invalidatedCount = await invalidatedRows.count();
        console.log(`Invalidated cache types found: ${invalidatedCount}`);

        for (let i = 0; i < invalidatedCount; i++) {
            const row = invalidatedRows.nth(i);
            const checkbox = row.locator(
                'input[type="checkbox"][data-role="select-row"]'
            );

            const cacheType = await checkbox.getAttribute('value');
            console.log(`Selecting invalidated cache type: ${cacheType}`);

            await checkbox.check();
            await expect(checkbox).toBeChecked();
        }

        /*
         * Flush Magento Cache.
         */
        await flushMagentoButton.click();

        const flushSuccessMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(flushSuccessMessage).toBeVisible({ timeout: 60000 });
        await expect(flushSuccessMessage).toContainText(
            'The Magento cache storage has been flushed.'
        );

        await expect(invalidatedRows).toHaveCount(0, { timeout: 60000 });

        /* =============================================================
         * PART 2: STOREFRONT - CREDIT CARD CHECKOUT
         * ============================================================= */

        /*
         * Customer sign in.
         */
        await page.goto(`${storefrontUrl}/customer/account/login/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        await page.getByRole('textbox', {
            name: 'Email'
        }).fill(customerEmail);

        await page.getByRole('textbox', {
            name: 'Password',
            exact: true
        }).fill(customerPassword);

        await page.getByRole('button', {
            name: 'Sign In',
            exact: true
        }).click();

        await expect(page).not.toHaveURL(
            /customer\/account\/login/,
            {
                timeout: 30000
            }
        );

        /*
         * Open product.
         */
        await page.goto(
            `${storefrontUrl}/chocolate-protein-powder-1.html`,
            {
                waitUntil: 'networkidle'
            }
        );

        const addToCartButton = page.getByRole('button', {
            name: 'Add to Cart'
        });

        await expect(addToCartButton).toBeEnabled({
            timeout: 30000
        });

        await addToCartButton.click();

        await expect(
            page.getByText(/added .* to your shopping cart/i)
        ).toBeVisible({
            timeout: 30000
        });

        /*
         * Go to checkout.
         */
        await page.goto(`${storefrontUrl}/checkout/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        /*
         * Select first saved shipping address.
         */
        const shippingAddressItems = page.locator(
            '.shipping-address-item'
        );

        await expect(shippingAddressItems.first()).toBeVisible({
            timeout: 60000
        });

        const firstShippingAddress = shippingAddressItems.first();

        const addressIsSelected = await firstShippingAddress.evaluate(
            element => element.classList.contains('selected-item')
        );

        if (!addressIsSelected) {
            const shipHereButton = firstShippingAddress.getByRole(
                'button',
                {
                    name: /Ship Here/i
                }
            );

            if (await shipHereButton.isVisible()) {
                await shipHereButton.click();
            } else {
                await firstShippingAddress.click();
            }
        }

        /*
         * Verify expected shipping address.
         */
        await expect(firstShippingAddress).toContainText('Jean Pierre');
        await expect(firstShippingAddress).toContainText('100 Connell Drive');
        await expect(firstShippingAddress).toContainText(/Berkley Heights/i);
        await expect(firstShippingAddress).toContainText('07922');
        await expect(firstShippingAddress).toContainText('9089089081');

        /*
         * Select first available shipping method.
         */
        const shippingMethods = page.locator(
            '#checkout-shipping-method-load input[type="radio"]'
        );

        await expect(shippingMethods.first()).toBeVisible({
            timeout: 60000
        });

        const checkedShippingMethod = page.locator(
            '#checkout-shipping-method-load input[type="radio"]:checked'
        );

        if (!(await checkedShippingMethod.count())) {
            await shippingMethods.first().check();
        }

        /*
         * Continue to Review & Payments.
         */
        const shippingNextButton = page.getByRole('button', {
            name: 'Next',
            exact: true
        });

        await expect(shippingNextButton).toBeEnabled({
            timeout: 30000
        });

        await shippingNextButton.click();

        /*
         * Select CommerceHub Credit/Debit Card.
         */
        const commerceHubPaymentMethod = page.getByRole('radio', {
            name: 'Credit/Debit Card',
            exact: true
        });

        await expect(commerceHubPaymentMethod).toBeVisible({
            timeout: 60000
        });

        await commerceHubPaymentMethod.click();

        await expect(commerceHubPaymentMethod).toBeChecked({
            timeout: 30000
        });

        /*
         * Wait for Fiserv to mount the hosted Card Number iframe.
         */
        const cardNumberContainer = page.locator(
            '#fiserv_commercehub-card-number'
        );

        await expect(cardNumberContainer).toBeVisible({
            timeout: 60000
        });

        await expect(cardNumberContainer.locator('iframe')).toBeAttached({
            timeout: 60000
        });

        /*
         * Fill hosted card fields.
         */
        await fillHostedField(
            page,
            '#fiserv_commercehub-card-number',
            '4111111111111111'
        );

        await fillHostedField(
            page,
            '#fiserv_commercehub-name-on-card',
            'Jean Pierre'
        );

        await fillHostedField(
            page,
            '#fiserv_commercehub-security-code',
            '111'
        );

        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-month',
            '#expirationMonth',
            '12'
        );

        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-year',
            '#expirationYear',
            '2032'
        );

        /*
         * Place the order.
         */
        const placeOrderButton = page.getByRole('button', {
            name: 'Place Order',
            exact: true
        });

        await expect(placeOrderButton).toBeEnabled({
            timeout: 60000
        });

        await placeOrderButton.click();

        /*
         * Verify Magento order success.
         */
        await expect(page).toHaveURL(
            /\/checkout\/onepage\/success\/?/,
            {
                timeout: 120000
            }
        );

        await expect(
            page.getByText(/thank you for your purchase/i)
        ).toBeVisible({
            timeout: 30000
        });

        /*
         * Capture the order number from the success page so we can find
         * this exact order in the Admin Orders grid.
         * Signed-in customers see it as a link: <a class="order-number">
         */
        const orderNumberElement = page
            .locator('.checkout-success')
            .locator('.order-number, strong')
            .filter({ hasText: /\d{6,}/ })
            .first();

        await expect(orderNumberElement).toBeVisible({ timeout: 30000 });

        const orderNumberText = await orderNumberElement.innerText();
        const orderNumber = orderNumberText.match(/\d{6,}/)[0];

        console.log(`Order placed: #${orderNumber}`);

        /* =============================================================
         * PART 3: ADMIN - SALES > ORDERS
         * ============================================================= */

        /*
         * Go back to the admin panel. The admin session from Part 1 is
         * usually still active; sign in again only if the login form shows.
         */
        await page.goto(adminUrl, {
            waitUntil: 'load',
            timeout: 60000
        });

        const adminLoginForm = page.locator('#username');

        if (await adminLoginForm.isVisible()) {
            await adminLoginForm.fill(adminUsername);
            await page.locator('#login').fill(adminPassword);
            await page.getByText('Sign in', { exact: true }).click();
            await expect(adminLoginForm).toBeHidden({ timeout: 60000 });
            await page.waitForLoadState('load');
        }

        /*
         * Open Sales > Orders.
         * Scoped to Magento's Sales menu item so we never match other
         * "Sales" or "Orders" links (e.g. the Sales config tab or Reports).
         */
        const salesMenuItem = page.locator('#menu-magento-sales-sales');
        const salesMenu = salesMenuItem.locator('> a');
        const ordersLink = salesMenuItem
            .locator('.submenu a')
            .filter({ hasText: /^\s*Orders\s*$/ });

        await expect(salesMenu).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await ordersLink.isVisible())) {
                await salesMenu.click();
            }

            await expect(ordersLink).toBeVisible({ timeout: 3000 });
        }).toPass({
            timeout: 60000,
            intervals: [1000, 2000, 3000]
        });

        await ordersLink.click();

        await expect(page).toHaveURL(/\/sales\/order\/index\//, {
            timeout: 60000
        });

        await expect(
            page.getByRole('heading', { name: 'Orders', level: 1 })
        ).toBeVisible({ timeout: 60000 });

        /* =============================================================
         * PART 4: ADMIN - OPEN THE ORDER WE JUST PLACED
         * ============================================================= */

        /*
         * The Orders grid is a Magento UI component that loads rows via
         * AJAX after the page loads. Wait for the loading spinner to clear.
         */
        const gridSpinner = page.locator(
            '[data-role="spinner"]' +
            '[data-component="sales_order_grid.sales_order_grid.sales_order_columns"]'
        );

        await expect(gridSpinner).toBeHidden({ timeout: 60000 });

        /*
         * Search the grid by order number so the order is guaranteed to
         * be on the first page, regardless of sorting or pagination.
         */
        const gridSearch = page.getByRole('textbox', {
            name: 'Search by keyword'
        });

        await expect(gridSearch).toBeVisible({ timeout: 60000 });
        await gridSearch.fill(orderNumber);
        await gridSearch.press('Enter');

        await expect(gridSpinner).toBeHidden({ timeout: 60000 });

        /*
         * Find the grid row for our order and click its View link.
         */
        const orderRow = page
            .locator('tr.data-row')
            .filter({ hasText: orderNumber });

        await expect(orderRow).toHaveCount(1, { timeout: 60000 });

        const viewLink = orderRow.locator('a.action-menu-item', {
            hasText: /^\s*View\s*$/
        });

        await expect(viewLink).toBeVisible({ timeout: 30000 });

        const orderViewHref = await viewLink.getAttribute('href');
        console.log(`Opening order view: ${orderViewHref}`);

        await viewLink.click();

        /*
         * Verify we landed on this order's view page.
         */
        await expect(page).toHaveURL(
            /\/sales\/order\/view\/order_id\/\d+\//,
            {
                timeout: 60000
            }
        );

        await expect(
            page.getByRole('heading', { level: 1 })
        ).toContainText(orderNumber, { timeout: 60000 });

        console.log(`Viewing Sale order #${orderNumber}.`);

        /*
         * Sale (Auth & Capture) already captured the payment and Magento
         * created the invoice automatically at checkout, so there is no
         * Invoice step. Go straight to the existing invoice and refund it.
         */

        /* =============================================================
         * PART 5: ADMIN - OPEN THE INVOICE
         * ============================================================= */

        /*
         * Wait for the order view page to fully load so Magento's jQuery
         * tabs are bound. Clicking a tab before that does nothing.
         */
        await page.waitForLoadState('load');

        /*
         * Magento puts id="sales_order_view_tabs_order_invoices" on both the
         * tab <li> and its <a>, so target the <a> by its stable data-ui-id.
         */
        const invoicesTab = page.locator(
            'a[data-ui-id="sales-order-tabs-tab-link-order-invoices"]'
        );

        /*
         * Invoice "View" link inside the Invoices tab. Matched by its href
         * so it can never match an order or credit memo View link.
         */
        const invoiceViewLink = page
            .locator('a.action-menu-item[href*="/sales/order_invoice/view/"]')
            .first();

        await expect(invoicesTab).toBeVisible({ timeout: 60000 });

        /*
         * Click the Invoices tab and retry until the invoice grid has loaded
         * and the View link is visible. Only click again while the link is
         * still hidden, so the tab is never toggled away.
         */
        await expect(async () => {
            if (!(await invoiceViewLink.isVisible())) {
                await invoicesTab.click();
            }

            await expect(invoiceViewLink).toBeVisible({ timeout: 5000 });
        }).toPass({
            timeout: 90000,
            intervals: [1000, 2000, 3000]
        });

        console.log(
            `Opening invoice: ${await invoiceViewLink.getAttribute('href')}`
        );

        await invoiceViewLink.click();

        await expect(page).toHaveURL(
            /\/sales\/order_invoice\/view\/invoice_id\/\d+\//,
            { timeout: 60000 }
        );

        /* =============================================================
         * PART 6: ADMIN - CREDIT MEMO (REFUND)
         * ============================================================= */

        await page.waitForLoadState('load');

        const creditMemoButton = page.locator(
            'button[data-ui-id="sales-invoice-view-credit-memo-button"]'
        );

        await expect(creditMemoButton).toBeVisible({ timeout: 60000 });
        await expect(creditMemoButton).toBeEnabled({ timeout: 30000 });

        await expect(async () => {
            if (!/\/sales\/order_creditmemo\/new\//.test(page.url())) {
                await creditMemoButton.click();
            }

            await expect(page).toHaveURL(
                /\/sales\/order_creditmemo\/new\//,
                { timeout: 10000 }
            );
        }).toPass({
            timeout: 60000,
            intervals: [1000, 2000, 3000]
        });

        await page.waitForLoadState('load');

        /*
         * Online Refund button. Its id is regenerated on every page load,
         * and the page may also show "Refund Offline", so match the exact
         * title "Refund" plus the stable data-ui-id.
         */
        const refundButton = page.locator(
            'button[data-ui-id="order-items-submit-button"][title="Refund"]'
        );

        await expect(refundButton).toBeVisible({ timeout: 60000 });
        await expect(refundButton).toBeEnabled({ timeout: 30000 });
        await refundButton.click();

        await expect(page).toHaveURL(
            /\/sales\/order\/view\/order_id\/\d+\//,
            { timeout: 120000 }
        );

        const refundSuccessMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(refundSuccessMessage).toBeVisible({ timeout: 60000 });
        await expect(refundSuccessMessage).toContainText(
            'You created the credit memo.'
        );

        console.log(`Order #${orderNumber} refunded.`);
    }
);
