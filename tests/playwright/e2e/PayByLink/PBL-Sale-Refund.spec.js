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

/*
 * Pay By Link settings in Stores > Configuration > Payment Methods.
 * paymentAction: 'authorize' = Authorize,
 *                'authorize_capture' = Sale (Auth & Capture).
 */
const payByLinkSettings = {
    paymentPageId: '4e27c4e5-9854-40e1-bc41-9d7bdf0c62a2',
    paymentAction: 'authorize_capture'
};

/*
 * Admin order data.
 * customerId: entity_id of the customer row picked in the customer grid.
 * productId: value of the product checkbox in the Add Products grid.
 */
const pblOrder = {
    customerId: '1',
    productId: '3',
    shippingMethod: 'flatrate_flatrate',
    paymentMethod: 'fiserv_pay_by_link'
};

/*
 * Card and billing details entered on the CommerceHub hosted payment page.
 */
const hppPayment = {
    nameOnCard: 'Jean Pierre',
    cardNumber: '4000000000002701',
    expiration: '12/2031',
    securityCode: '123',
    firstName: 'Jean',
    lastName: 'Pierre',
    street: '123 Main St',
    city: 'Berkley Heights',
    state: 'New Jersey',
    postalCode: '07922',
    country: 'United States'
};

/*
 * Magento's admin order-create page reloads blocks via AJAX and shows
 * a loading mask while it works. Wait until no visible mask remains.
 */
async function waitForAdminLoader(page) {
    await expect(
        page.locator('.loading-mask:visible, #loading-mask:visible')
    ).toHaveCount(0, { timeout: 60000 });
}

/*
 * The hosted payment page renders some fields inside Fiserv SDC iframes.
 * Search the page and every frame in it until a visible match is found.
 */
async function findInFrames(page, selector, description = selector) {
    let found;

    await expect.poll(
        async () => {
            for (const frame of page.frames()) {
                try {
                    const candidate = frame.locator(selector).first();

                    if (await candidate.isVisible()) {
                        found = candidate;
                        return true;
                    }
                } catch {
                    /* Frame detached mid-search; keep polling. */
                }
            }

            return false;
        },
        {
            timeout: 60000,
            intervals: [500, 1000, 2000],
            message: `Waiting for hosted payment page field: ${description}`
        }
    ).toBe(true);

    return found;
}

/*
 * Pick a value from one of the hosted payment page's combobox dropdowns
 * (State, Country). Type to filter, then click the exact matching option.
 */
async function selectComboboxOption(page, placeholder, optionText) {
    const combobox = page.locator(
        `input[role="combobox"][placeholder="${placeholder}"]`
    );

    await expect(combobox).toBeVisible({ timeout: 30000 });
    await combobox.click();
    await combobox.fill(optionText);

    const option = page.getByRole('option', {
        name: optionText,
        exact: true
    });

    await expect(option).toBeVisible({ timeout: 30000 });
    await option.click();

    await expect(combobox).toHaveValue(optionText, { timeout: 10000 });
}

test(
    `PBL Sale Refund - MID ${credentials.merchantId}`,
    async ({ page }) => {
        test.setTimeout(900000);

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
         * Pay By Link: set the Default Payment Page ID and Charge Type.
         * Expand the section only if its fields are hidden, so we never
         * toggle an already-open section closed.
         */
        const payByLinkSection = page.locator(
            '#payment_us_fiserv_payments_fiserv_pay_by_link-head'
        );

        const paymentPageIdInput = page.locator(
            '#payment_us_fiserv_payments_fiserv_pay_by_link_payment_page_id'
        );

        const payByLinkPaymentAction = page.locator(
            '#payment_us_fiserv_payments_fiserv_pay_by_link_payment_action'
        );

        await expect(payByLinkSection).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await paymentPageIdInput.isVisible())) {
                await payByLinkSection.click();
            }

            await expect(paymentPageIdInput).toBeVisible({ timeout: 3000 });
        }).toPass({
            timeout: 30000,
            intervals: [1000, 2000, 3000]
        });

        /*
         * If Magento's "Use system value" checkbox is ticked for either
         * field, the field is disabled. Untick it so it can be changed.
         */
        for (const inheritSelector of [
            '#payment_us_fiserv_payments_fiserv_pay_by_link_payment_page_id_inherit',
            '#payment_us_fiserv_payments_fiserv_pay_by_link_payment_action_inherit'
        ]) {
            const inheritCheckbox = page.locator(inheritSelector);

            if (
                (await inheritCheckbox.count()) &&
                (await inheritCheckbox.isChecked())
            ) {
                await inheritCheckbox.uncheck();
            }
        }

        /*
         * Default Payment Page ID.
         */
        await expect(paymentPageIdInput).toBeEnabled({ timeout: 30000 });
        await paymentPageIdInput.fill(payByLinkSettings.paymentPageId);
        await expect(paymentPageIdInput).toHaveValue(
            payByLinkSettings.paymentPageId
        );

        /*
         * Charge Type.
         */
        await expect(payByLinkPaymentAction).toBeVisible({ timeout: 30000 });
        await expect(payByLinkPaymentAction).toBeEnabled({ timeout: 30000 });
        await payByLinkPaymentAction.selectOption(
            payByLinkSettings.paymentAction
        );
        await expect(payByLinkPaymentAction).toHaveValue(
            payByLinkSettings.paymentAction
        );

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
         * PART 2: ADMIN - SALES > ORDERS
         * ============================================================= */

        /*
         * Wait for the Cache Management page to fully load so Magento's
         * admin menu click handlers are attached before clicking Sales.
         */
        await page.waitForLoadState('load');

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

        await page.waitForLoadState('load');

        /* =============================================================
         * PART 3: ADMIN - CREATE NEW ORDER
         * ============================================================= */

        const createNewOrderButton = page.locator(
            'button#add[data-ui-id="add-button"]'
        );

        await expect(createNewOrderButton).toBeVisible({ timeout: 60000 });
        await createNewOrderButton.click();

        await expect(page).toHaveURL(/\/sales\/order_create\//, {
            timeout: 60000
        });

        await page.waitForLoadState('load');
        await waitForAdminLoader(page);

        /*
         * Select the customer by their ID cell in the customer grid.
         */
        const customerIdCell = page
            .locator('td[data-column="entity_id"]')
            .filter({ hasText: new RegExp(`^\\s*${pblOrder.customerId}\\s*$`) })
            .first();

        await expect(customerIdCell).toBeVisible({ timeout: 60000 });
        await customerIdCell.click();

        await expect(page).toHaveURL(/\/sales\/order_create\/index\//, {
            timeout: 60000
        });

        await waitForAdminLoader(page);

        /*
         * Add Products.
         */
        const addProductsButton = page.locator('#add_products');

        await expect(addProductsButton).toBeVisible({ timeout: 60000 });
        await addProductsButton.click();

        await waitForAdminLoader(page);

        const productCheckbox = page.locator(
            `input.admin__control-checkbox[type="checkbox"][value="${pblOrder.productId}"]`
        );

        await expect(productCheckbox).toBeVisible({ timeout: 60000 });
        await productCheckbox.check();
        await expect(productCheckbox).toBeChecked();

        /*
         * Add Selected Product(s) to Order.
         * Its id is regenerated on every page load, so match its title.
         */
        const addSelectedProductsButton = page.locator(
            'button[title="Add Selected Product(s) to Order"]'
        );

        await expect(addSelectedProductsButton).toBeVisible({ timeout: 30000 });
        await addSelectedProductsButton.click();

        await waitForAdminLoader(page);

        await expect(
            page.locator('#order-items_grid tbody')
        ).toBeVisible({ timeout: 60000 });

        /*
         * Get shipping methods and rates.
         */
        const getShippingRatesLink = page
            .locator('a.action-default')
            .filter({ hasText: 'Get shipping methods and rates' });

        await expect(getShippingRatesLink).toBeVisible({ timeout: 60000 });
        await getShippingRatesLink.click();

        await waitForAdminLoader(page);

        const flatRateShipping = page.locator(
            `#s_method_${pblOrder.shippingMethod}`
        );

        await expect(flatRateShipping).toBeVisible({ timeout: 60000 });
        await flatRateShipping.check();

        await waitForAdminLoader(page);

        await expect(flatRateShipping).toBeChecked({ timeout: 30000 });

        /*
         * Pay by Link payment method. Magento reloads the payment block
         * after shipping is chosen, so retry until the radio sticks.
         */
        const payByLinkLabel = page.locator(
            `label[for="p_method_${pblOrder.paymentMethod}"]`
        );
        const payByLinkRadio = page.locator(
            `#p_method_${pblOrder.paymentMethod}`
        );

        await expect(payByLinkLabel).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await payByLinkRadio.isChecked())) {
                await payByLinkLabel.click();
                await waitForAdminLoader(page);
            }

            await expect(payByLinkRadio).toBeChecked({ timeout: 3000 });
        }).toPass({
            timeout: 30000,
            intervals: [1000, 2000, 3000]
        });

        /* =============================================================
         * PART 4: ADMIN - SUBMIT THE ORDER
         * ============================================================= */

        const submitOrderButton = page.locator('#submit_order_top_button');

        await expect(submitOrderButton).toBeVisible({ timeout: 60000 });
        await expect(submitOrderButton).toBeEnabled({ timeout: 30000 });
        await submitOrderButton.click();

        await expect(page).toHaveURL(
            /\/sales\/order\/view\/order_id\/\d+\//,
            { timeout: 120000 }
        );

        const orderSuccessMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(orderSuccessMessage).toBeVisible({ timeout: 60000 });
        await expect(orderSuccessMessage).toContainText(
            'You created the order.'
        );

        const orderHeading = page.getByRole('heading', { level: 1 });

        await expect(orderHeading).toContainText(/\d{6,}/, {
            timeout: 60000
        });

        const orderNumber = (await orderHeading.innerText()).match(/\d{6,}/)[0];

        console.log(`Pay by Link order created: #${orderNumber}`);

        /*
         * Remember this order's admin view URL so we can come back to it
         * after paying on the hosted payment page.
         */
        const orderViewUrl = page.url();

        /* =============================================================
         * PART 5: ADMIN - OPEN THE PAY BY LINK URL
         * ============================================================= */

        /*
         * Find the generated payment link in the order view. It is the
         * only <td> link pointing at the fimu.me short-link domain.
         */
        const paymentLink = page.locator('td a[href*="fimu.me/"]').first();

        await expect(paymentLink).toBeVisible({ timeout: 60000 });

        const paymentLinkUrl = await paymentLink.getAttribute('href');

        console.log(`Pay by Link URL: ${paymentLinkUrl}`);

        /*
         * Open the payment link directly in a new tab instead of clicking
         * the target="_blank" link. fimu.me redirects to the CommerceHub
         * hosted payment page.
         */
        const hppPage = await page.context().newPage();

        /*
         * Diagnostics: log if the hosted payment page navigates, closes,
         * or crashes on its own, so a failure shows exactly what happened.
         */
        const hppStart = Date.now();

        hppPage.on('framenavigated', frame => {
            if (frame === hppPage.mainFrame()) {
                console.log(`[HPP] Navigated to: ${frame.url()}`);
            }
        });

        hppPage.on('close', () => {
            console.log(
                `[HPP] Page closed ${Math.round((Date.now() - hppStart) / 1000)}s ` +
                'after opening.'
            );
        });

        hppPage.on('crash', () => {
            console.log('[HPP] Page crashed.');
        });

        await hppPage.goto(paymentLinkUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        await expect(hppPage).toHaveURL(
            /commercehub-checkout-cert\.fiservapps\.com\/hpp\//,
            { timeout: 60000 }
        );

        await hppPage.waitForLoadState('load');

        /* =============================================================
         * PART 6: HOSTED PAYMENT PAGE - PAY WITH CARD
         * Card is already the selected payment option, so no click needed.
         * ============================================================= */

        /*
         * Name on Card (Fiserv SDC iframe).
         */
        const nameOnCardInput = hppPage
            .locator('#fiserv-name-on-card iframe')
            .contentFrame()
            .locator('input')
            .first();

        await expect(nameOnCardInput).toBeVisible({ timeout: 60000 });
        await nameOnCardInput.fill(hppPayment.nameOnCard);

        /*
         * Card Number, Expiration, Security Code. These may live in the
         * page or in an SDC iframe, so search every frame.
         * fill() sets each value in a single step, which is faster and
         * less fragile than typing one key at a time.
         */
        const cardNumberInput = await findInFrames(
            hppPage,
            'input#cardNumber',
            'Card Number'
        );

        /*
         * Type the card number key by key. The field formats and validates
         * on each keystroke; fill() sets the value in one step and can skip
         * that validation, leaving the form invalid so Place order does
         * nothing.
         */
        await cardNumberInput.click();
        await cardNumberInput.pressSequentially(hppPayment.cardNumber, {
            delay: 30
        });

        /*
         * Expiration (MM / YYYY). The field formats as you type, so type
         * digits only and let it insert the separator.
         */
        const expirationInput = await findInFrames(
            hppPage,
            'input#expiration',
            'Card Expiration Date'
        );

        await expirationInput.click();
        await expirationInput.pressSequentially(
            hppPayment.expiration.replace(/\D/g, ''),
            { delay: 50 }
        );

        const securityCodeInput = await findInFrames(
            hppPage,
            'input#securityCode',
            'Card Security Code'
        );

        await securityCodeInput.click();
        await securityCodeInput.pressSequentially(hppPayment.securityCode, {
            delay: 50
        });

        /*
         * Billing name and address. The field ids are regenerated on every
         * load, so match by the stable name attribute.
         */
        const billingFields = [
            ['firstName', hppPayment.firstName],
            ['lastName', hppPayment.lastName],
            ['street', hppPayment.street],
            ['city', hppPayment.city]
        ];

        for (const [fieldName, value] of billingFields) {
            const input = hppPage.locator(`input[name="${fieldName}"]`);

            await expect(input).toBeVisible({ timeout: 30000 });
            await input.fill(value);
            await expect(input).toHaveValue(value);
        }

        await selectComboboxOption(hppPage, 'State', hppPayment.state);

        const postalCodeInput = hppPage.locator('input[name="postalCode"]');

        await expect(postalCodeInput).toBeVisible({ timeout: 30000 });
        await postalCodeInput.fill(hppPayment.postalCode);
        await expect(postalCodeInput).toHaveValue(hppPayment.postalCode);

        await selectComboboxOption(hppPage, 'Country', hppPayment.country);

        /*
         * Close any dropdown that is still open and blur the last field.
         * An open State/Country list can sit on top of Place order, and
         * the page validates fields on blur before enabling submit.
         */
        await hppPage.keyboard.press('Escape');
        await hppPage.keyboard.press('Tab');

        /*
         * Place order. Matched by its stable data-submit-btn attribute and
         * searched in every frame, in case the button lives in an iframe.
         */
        const placeOrderButton = await findInFrames(
            hppPage,
            'button[data-submit-btn="true"]',
            'Place order button'
        );

        await expect(placeOrderButton).toHaveAttribute(
            'aria-label',
            'Place order'
        );

        await placeOrderButton.scrollIntoViewIfNeeded();

        /*
         * Click Place order and retry until the form is submitted (the
         * button goes away). Only click again while it is still showing.
         * If it never submits, log any fields the page marks as invalid.
         */
        try {
            await expect(async () => {
                if (await placeOrderButton.isVisible()) {
                    await expect(placeOrderButton).toBeEnabled({
                        timeout: 5000
                    });
                    await placeOrderButton.click({ timeout: 5000 });
                }

                await expect(placeOrderButton).toBeHidden({
                    timeout: 15000
                });
            }).toPass({
                timeout: 120000,
                intervals: [1000, 2000, 3000]
            });
        } catch (error) {
            const invalidFields = await hppPage
                .locator('[aria-invalid="true"]')
                .evaluateAll(elements => elements.map(element =>
                    element.getAttribute('name') ||
                    element.getAttribute('aria-label') ||
                    element.id
                ));

            console.log(
                `[HPP] Place order did not submit. Invalid fields: ` +
                `${JSON.stringify(invalidFields)}`
            );

            /*
             * Save a screenshot of the payment page so any validation
             * message next to a field is visible after the run.
             */
            await hppPage.screenshot({
                path: test.info().outputPath('hpp-place-order-failed.png'),
                fullPage: true
            });

            throw error;
        }

        console.log(
            `Hosted payment page submitted for order #${orderNumber}. ` +
            `Final URL: ${hppPage.url()}`
        );

        /* =============================================================
         * PART 7: ADMIN - RETURN TO THE ORDER
         * ============================================================= */

        /*
         * Close the hosted payment page tab and go back to the admin order.
         */
        await hppPage.close();
        await page.bringToFront();

        /*
         * The order is created before the customer pays, so Magento only
         * gets the authorization after the hosted payment page completes.
         * With Sale (Auth & Capture), Magento creates the invoice itself
         * once the payment result arrives. Reload the order until the
         * Invoices tab lists that invoice.
         */
        const saleInvoicesTab = page.locator(
            'a[data-ui-id="sales-order-tabs-tab-link-order-invoices"]'
        );
        const saleInvoiceViewLink = page
            .locator('a.action-menu-item[href*="/sales/order_invoice/view/"]')
            .first();

        await expect(async () => {
            await page.goto(orderViewUrl, {
                waitUntil: 'load',
                timeout: 60000
            });

            await page.waitForLoadState('load');
            await saleInvoicesTab.click();
            await expect(saleInvoiceViewLink).toBeVisible({ timeout: 10000 });
        }).toPass({
            timeout: 180000,
            intervals: [5000, 10000, 15000]
        });

        await expect(
            page.getByRole('heading', { level: 1 })
        ).toContainText(orderNumber, { timeout: 60000 });

        /* =============================================================
         * PART 8: ADMIN - OPEN THE INVOICE
         * ============================================================= */

        await page.waitForLoadState('load');

        /*
         * Magento puts id="sales_order_view_tabs_order_invoices" on both the
         * tab <li> and its <a>, so target the <a> by its stable data-ui-id.
         */
        const invoicesTab = page.locator(
            'a[data-ui-id="sales-order-tabs-tab-link-order-invoices"]'
        );

        /*
         * Invoice "View" link, matched by its href so it can never match
         * an order or credit memo View link.
         */
        const invoiceViewLink = page
            .locator('a.action-menu-item[href*="/sales/order_invoice/view/"]')
            .first();

        await expect(invoicesTab).toBeVisible({ timeout: 60000 });

        /*
         * Click the Invoices tab and retry until the grid has loaded and
         * the View link is visible. Only click while it is still hidden.
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

        await invoiceViewLink.click();

        await expect(page).toHaveURL(
            /\/sales\/order_invoice\/view\/invoice_id\/\d+\//,
            { timeout: 60000 }
        );

        /* =============================================================
         * PART 9: ADMIN - CREDIT MEMO (REFUND)
         * ============================================================= */

        await page.waitForLoadState('load');

        const creditMemoButton = page.locator('#credit-memo');

        await expect(creditMemoButton).toBeVisible({ timeout: 60000 });
        await expect(creditMemoButton).toBeEnabled({ timeout: 30000 });
        await creditMemoButton.click();

        await expect(page).toHaveURL(
            /\/sales\/order_creditmemo\/new\//,
            { timeout: 60000 }
        );

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
