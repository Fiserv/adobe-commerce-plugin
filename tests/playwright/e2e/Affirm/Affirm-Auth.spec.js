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
    `Affirm Auth - MID ${credentials.merchantId}`,
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
    }
);
