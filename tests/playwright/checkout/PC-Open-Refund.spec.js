const { test, expect } = require('@playwright/test');

const storefrontUrl =
    'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

const adminUrl = `${storefrontUrl}/admin_gyx81dr`;

const adminUsername = 'tysonwin';
const adminPassword = 'password1';

const credentials = {
    merchantId: '100266000000162',
    terminalId: '10000001',
    apiKey: 'lAFGGTAMaDnZHkZPcs8euKxGHPIm5WQr',
    apiSecret: '6i4REVvypXZLAxqCeZLLHtLYYscw6zdEGs4nG14N00a'
};

const customerEmail = 'jean@pierre.com';

/*
 * Open refund data.
 */
const openRefund = {
    amount: '5.00',
    cardNumber: '4000000000002701',
    nameOnCard: 'Jean Pierre',
    securityCode: '123',
    expirationMonth: '12',
    expirationYear: '2035'
};

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
    `PC Open Refund - MID ${credentials.merchantId}`,
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
         * Credit/Debit Cards: set Payment Action to Authorize.
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
         * PART 2: ADMIN - SALES > OPEN REFUNDS
         * ============================================================= */

        /*
         * Wait for the Cache Management page to fully load so Magento's
         * admin menu click handlers are attached before clicking Sales.
         */
        await page.waitForLoadState('load');

        /*
         * Open Sales > Open Refunds.
         * The Open Refunds link is matched by its href inside the admin
         * navigation, so it can never match another "Sales" link.
         */
        const salesMenuItem = page.locator('#menu-magento-sales-sales');
        const salesMenu = salesMenuItem.locator('> a');
        const openRefundsLink = page
            .getByRole('navigation')
            .locator('a[href*="/fiserv/openrefund/index/"]');

        await expect(salesMenu).toBeVisible({ timeout: 60000 });

        await expect(async () => {
            if (!(await openRefundsLink.isVisible())) {
                await salesMenu.click();
            }

            await expect(openRefundsLink).toBeVisible({ timeout: 3000 });
        }).toPass({
            timeout: 60000,
            intervals: [1000, 2000, 3000]
        });

        await openRefundsLink.click();

        await expect(page).toHaveURL(/\/fiserv\/openrefund\/index\//, {
            timeout: 60000
        });

        await page.waitForLoadState('load');

        /* =============================================================
         * PART 3: ADMIN - CREATE NEW OPEN REFUND
         * ============================================================= */

        const createOpenRefundButton = page.locator(
            'a.open-refund-btn-create'
        );

        await expect(createOpenRefundButton).toBeVisible({ timeout: 60000 });
        await createOpenRefundButton.click();

        await expect(page).toHaveURL(/\/fiserv\/openrefund\/new\//, {
            timeout: 60000
        });

        await page.waitForLoadState('load');

        /*
         * a. Refund amount.
         */
        const amountInput = page.locator('#amount');

        await expect(amountInput).toBeVisible({ timeout: 60000 });
        await amountInput.fill(openRefund.amount);
        await expect(amountInput).toHaveValue(openRefund.amount);

        /*
         * b. Customer: search by email and select the first result.
         * The search input's own value is not page text, so getByText
         * only matches the suggestion results that appear below it.
         */
        const customerSearchInput = page.locator('#or-customer-search');

        await expect(customerSearchInput).toBeVisible({ timeout: 60000 });
        await customerSearchInput.click();
        await customerSearchInput.pressSequentially(customerEmail, {
            delay: 50
        });

        const firstCustomerResult = page
            .getByText(customerEmail)
            .first();

        await expect(firstCustomerResult).toBeVisible({ timeout: 30000 });
        await firstCustomerResult.click();

        /*
         * c. Source type: New Card (Hosted Fields).
         */
        const sourceTypeSelect = page.locator('#source_type');

        await expect(sourceTypeSelect).toBeVisible({ timeout: 60000 });
        await sourceTypeSelect.selectOption('HOSTED_FIELDS');
        await expect(sourceTypeSelect).toHaveValue('HOSTED_FIELDS');

        /*
         * d. Hosted card fields (same Fiserv SDC iframes as checkout).
         */
        await expect(
            page.locator('#fiserv_commercehub-card-number iframe')
        ).toBeAttached({ timeout: 60000 });

        await fillHostedField(
            page,
            '#fiserv_commercehub-card-number',
            openRefund.cardNumber
        );

        await fillHostedField(
            page,
            '#fiserv_commercehub-name-on-card',
            openRefund.nameOnCard
        );

        await fillHostedField(
            page,
            '#fiserv_commercehub-security-code',
            openRefund.securityCode
        );

        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-month',
            '#expirationMonth',
            openRefund.expirationMonth
        );

        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-year',
            '#expirationYear',
            openRefund.expirationYear
        );

        /* =============================================================
         * PART 4: ADMIN - SUBMIT THE OPEN REFUND
         * ============================================================= */

        const submitRefundButton = page.locator('#or-submit-btn');

        await expect(submitRefundButton).toBeVisible({ timeout: 60000 });
        await expect(submitRefundButton).toHaveClass(/or-ready/, {
            timeout: 60000
        });
        await expect(submitRefundButton).toBeEnabled({ timeout: 30000 });

        await submitRefundButton.click();

        /*
         * Wait for Magento's result message, then require it to be a
         * success message (an error message fails the test with its text).
         */
        const resultMessage = page.locator(
            '[data-ui-id="messages-message-success"], ' +
            '[data-ui-id="messages-message-error"]'
        ).first();

        await expect(resultMessage).toBeVisible({ timeout: 120000 });

        console.log(`Open refund result: ${await resultMessage.innerText()}`);

        await expect(
            resultMessage,
            'Open refund did not return a success message'
        ).toHaveAttribute('data-ui-id', 'messages-message-success');

        console.log(
            `Open refund of $${openRefund.amount} submitted for ${customerEmail}.`
        );
    }
);
