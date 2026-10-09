const { test, expect } = require('@playwright/test');

const storefrontUrl =
    'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

const adminUrl = `${storefrontUrl}/admin_gyx81dr`;

const adminUsername = 'tysonwin';
const adminPassword = 'password1';

const credentials = {
    merchantId: '100464000000064',
    terminalId: '10000001',
    apiKey: 't5zuAzIVhLxWE98voFWHZqlVWODw8K0AnhXSPJTny85pK2KB',
    apiSecret: '4ZH7LSbj21pZpJ7JdtRYXFSaF5dL5GlB9BpF0HjRbZ3qiB05P8P1VNV79kB1fREp'
};

const customerEmail = 'jean@pierre.com';
const customerPassword = 'Password1';

/*
 * Affirm sandbox buyer details.
 */
const affirmBuyer = {
    phone: '(678) 446-5240',
    passcode: '123456'
};

/*
 * Affirm renders its checkout in a different surface than Magento
 * (an iframe modal, a popup window, or a redirect). Search every open
 * page in the browser context, and every frame inside each page, until
 * a visible element matching the selector is found.
 */
async function findAffirmElement(page, selector, options = {}) {
    const { hasText, timeout = 60000, description = selector } = options;
    let found;

    await expect.poll(
        async () => {
            for (const openPage of page.context().pages()) {
                if (openPage.isClosed()) {
                    continue;
                }

                for (const frame of openPage.frames()) {
                    let locator = frame.locator(selector);

                    if (hasText) {
                        locator = locator.filter({ hasText });
                    }

                    try {
                        const candidate = locator.first();

                        if (await candidate.isVisible()) {
                            found = candidate;
                            return true;
                        }
                    } catch {
                        /* Frame detached mid-search; keep polling. */
                    }
                }
            }

            return false;
        },
        {
            timeout,
            intervals: [500, 1000, 2000],
            message: `Waiting for Affirm element: ${description}`
        }
    ).toBe(true);

    return found;
}

test(
    `Affirm Auth Capture Refund - MID ${credentials.merchantId}`,
    async ({ page }) => {
        test.setTimeout(480000);

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
         * Affirm: set Payment Action to Authorize.
         * Expand the section only if the Payment Action select is hidden,
         * so we never toggle an already-open section closed.
         */
        const affirmSection = page.locator(
            '#payment_us_fiserv_payments_fiserv_affirm-head'
        );

        const paymentActionSelect = page.locator(
            '#payment_us_fiserv_payments_fiserv_affirm_payment_action'
        );

        await expect(affirmSection).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await paymentActionSelect.isVisible())) {
                await affirmSection.click();
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
            '#payment_us_fiserv_payments_fiserv_affirm_payment_action_inherit'
        );

        if (
            (await paymentActionInherit.count()) &&
            (await paymentActionInherit.isChecked())
        ) {
            await paymentActionInherit.uncheck();
        }

        await expect(paymentActionSelect).toBeEnabled({ timeout: 30000 });

        await paymentActionSelect.selectOption('authorize');

        await expect(paymentActionSelect).toHaveValue('authorize');

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
         * PART 2: STOREFRONT - AFFIRM CHECKOUT
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
         * Select CommerceHub Affirm.
         */
        const affirmPaymentMethod = page.locator('#fiserv_affirm');

        await expect(affirmPaymentMethod).toBeVisible({ timeout: 60000 });
        await affirmPaymentMethod.click();
        await expect(affirmPaymentMethod).toBeChecked({ timeout: 30000 });

        /*
         * Wait for the Fiserv SDK to render the Affirm button inside
         * #affirm-button-container, then click it.
         */
        await expect(page.locator('#affirm-button-container')).toBeVisible({
            timeout: 60000
        });

        const affirmButton = await findAffirmElement(
            page,
            'button.affirm-checkout-btn',
            { description: 'Pay over time with Affirm button' }
        );

        await expect(affirmButton).toBeEnabled({ timeout: 30000 });
        await affirmButton.click();

        /*
         * Affirm sign-in: mobile number.
         */
        const phoneInput = await findAffirmElement(
            page,
            'input[data-testid="phone-number-field"]',
            { timeout: 90000, description: 'Mobile number field' }
        );

        await phoneInput.fill('');
        await phoneInput.pressSequentially(affirmBuyer.phone, { delay: 50 });

        const phoneContinueButton = await findAffirmElement(
            page,
            'button[data-testid="submit-button"]',
            { hasText: /^\s*Continue\s*$/, description: 'Continue button' }
        );

        await phoneContinueButton.click();

        /*
         * Affirm sign-in: passcode.
         */
        const passcodeInput = await findAffirmElement(
            page,
            [
                'input[data-testid="phone-pin-field"]',
                'input[autocomplete="one-time-code"]',
                'input[aria-label*="code" i]',
                'input[name*="pin" i]'
            ].join(', '),
            { timeout: 90000, description: 'Passcode field' }
        );

        await passcodeInput.pressSequentially(affirmBuyer.passcode, {
            delay: 100
        });

        /*
         * Choose the first payment plan option.
         */
        const planOption = await findAffirmElement(
            page,
            'div[data-testid="indicator"].indicator-type-radio',
            { timeout: 90000, description: 'Payment plan radio' }
        );

        await planOption.click();

        /*
         * Accept the disclosure checkbox.
         */
        const disclosureCheckbox = await findAffirmElement(
            page,
            'div[data-testid="disclosure-checkbox-indicator"]',
            { timeout: 60000, description: 'Disclosure checkbox' }
        );

        await disclosureCheckbox.click();

        /*
         * Confirm the Affirm loan. Affirm then hands control back to
         * Magento, which places the order.
         */
        const confirmButton = await findAffirmElement(
            page,
            'button[data-testid="submit-button"]',
            { hasText: /^\s*Confirm\s*$/, description: 'Confirm button' }
        );

        await expect(confirmButton).toBeEnabled({ timeout: 30000 });
        await confirmButton.click();

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

        /* =============================================================
         * PART 5: ADMIN - INVOICE (CAPTURE) THE AUTHORIZATION
         * ============================================================= */

        await page.waitForLoadState('load');

        const invoiceButton = page.locator('#order_invoice');

        await expect(invoiceButton).toBeVisible({ timeout: 60000 });
        await expect(invoiceButton).toBeEnabled({ timeout: 30000 });
        await invoiceButton.click();

        await expect(page).toHaveURL(
            /\/sales\/order_invoice\/new\/order_id\/\d+\//,
            { timeout: 60000 }
        );

        await page.waitForLoadState('load');

        /*
         * Make sure the invoice captures online through CommerceHub
         * (only applies if Magento shows the capture select).
         */
        const captureCaseSelect = page.locator(
            'select[name="invoice[capture_case]"]'
        );

        if (await captureCaseSelect.isVisible()) {
            await captureCaseSelect.selectOption('online');
            await expect(captureCaseSelect).toHaveValue('online');
        }

        /*
         * The Submit Invoice button id is regenerated on every page load,
         * so target its stable data-ui-id and title instead.
         */
        const submitInvoiceButton = page.locator(
            'button[data-ui-id="order-items-submit-button"][title="Submit Invoice"]'
        );

        await expect(submitInvoiceButton).toBeVisible({ timeout: 60000 });
        await expect(submitInvoiceButton).toBeEnabled({ timeout: 30000 });
        await submitInvoiceButton.click();

        await expect(page).toHaveURL(
            /\/sales\/order\/view\/order_id\/\d+\//,
            { timeout: 120000 }
        );

        const invoiceSuccessMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(invoiceSuccessMessage).toBeVisible({ timeout: 60000 });
        await expect(invoiceSuccessMessage).toContainText(
            'The invoice has been created.'
        );

        console.log(`Order #${orderNumber} invoiced (captured).`);

        /* =============================================================
         * PART 6: ADMIN - OPEN THE INVOICE
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
         * PART 7: ADMIN - CREDIT MEMO (REFUND)
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
