// This script was made to allow a tester to change MID credentials to use the following MID:
// Merchant ID: 100367000000275
const { test, expect } = require('@playwright/test');

test(
    'Admin user can update Commerce Hub Gateway credentials',
    async ({ page }) => {
        test.setTimeout(180000);

        const adminUrl =
            'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud/admin_gyx81dr';

        const adminUsername = 'tysonwin';
        const adminPassword = 'password1';

        const credentials = {
            merchantId: '100367000000275',
            terminalId: '10000001',
            apiKey: 's4C6s9ACBjvhspztLG7rXtTww7OugDXyA7HHSvSTmm1dSys2',
            apiSecret: 'oXUaAhFTiochiQYOpYAT0xNTAFrtDyDBCjnT4ooOKUGpWOlsFw1UdtCA6rzYyFvK'
        };

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
         * Wait for the Dashboard to fully load. Magento binds the admin
         * menu flyout click handlers with JavaScript after the page loads.
         * Clicking before that happens does nothing, which is why Stores
         * previously had to be clicked manually.
         */
        await page.waitForLoadState('load');
        await expect(
            page.getByRole('heading', { name: 'Dashboard', level: 1 })
        ).toBeVisible({ timeout: 60000 });

        /*
         * Open Stores > Configuration.
         * Scope everything to Magento's Stores menu item so we never match
         * "All Stores" or other Configuration links.
         */
        const storesMenuItem = page.locator('#menu-magento-backend-stores');
        const storesMenu = storesMenuItem.locator('> a');
        const configurationLink = storesMenuItem
            .locator('.submenu a')
            .filter({ hasText: /^\s*Configuration\s*$/ });

        await expect(storesMenu).toBeVisible({ timeout: 60000 });

        /*
         * Click Stores and retry until the flyout is actually open.
         * Only click again if Configuration is still hidden, so we never
         * toggle the flyout closed.
         */
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
         * Save Magento configuration.
         */
        const saveConfigButton = page.getByText('Save Config', {
            exact: true
        });

        await expect(saveConfigButton).toBeVisible({ timeout: 30000 });
        await saveConfigButton.click();

        /*
         * Verify Magento reports a successful configuration save.
         */
        const successMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(successMessage).toBeVisible({ timeout: 60000 });
        await expect(successMessage).toHaveText(
            'You saved the configuration.'
        );

        /*
         * Magento invalidates cache types after configuration changes.
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
         * Cache Management: find every row whose status is
         * <span>Invalidated</span> and tick that row's checkbox.
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
         * Flush Magento Cache to confirm the changes.
         */
        await flushMagentoButton.click();

        const flushSuccessMessage = page.locator(
            '[data-ui-id="messages-message-success"]'
        );

        await expect(flushSuccessMessage).toBeVisible({ timeout: 60000 });
        await expect(flushSuccessMessage).toContainText(
            'The Magento cache storage has been flushed.'
        );

        /*
         * After the flush, no cache type should still be Invalidated.
         */
        await expect(invalidatedRows).toHaveCount(0, { timeout: 60000 });
    }
);
