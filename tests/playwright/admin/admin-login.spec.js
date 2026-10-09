const { test, expect } = require('@playwright/test');

test(
    'Admin user can sign in to the Adobe Commerce admin panel',
    async ({ page }) => {
        test.setTimeout(120000);

        const adminUrl = 'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud/admin_gyx81dr';
        const adminUsername = 'tysonwin';
        const adminPassword = 'password1';

        await page.goto(adminUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        const usernameInput = page.locator('#username');
        const passwordInput = page.locator('#login');

        await expect(usernameInput).toBeVisible({
            timeout: 60000
        });

        await expect(passwordInput).toBeVisible({
            timeout: 60000
        });

        await usernameInput.fill(adminUsername);
        await passwordInput.fill(adminPassword);

        await expect(usernameInput).toHaveValue(adminUsername);

        const signInButton = page.getByText(
            'Sign in',
            {
                exact: true
            }
        );

        await expect(signInButton).toBeVisible({
            timeout: 30000
        });

        await expect(signInButton).toBeEnabled({
            timeout: 30000
        });

        await signInButton.click();

        await expect(usernameInput).toBeHidden({
            timeout: 60000
        });

        await expect(page).not.toHaveURL(
            /auth\/login/i,
            {
                timeout: 60000
            }
        );
    }
);
