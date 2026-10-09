const { test, expect } = require('@playwright/test');

/*
 * Use an absolute storefront URL so the test works in every project
 * (chromium, firefox, webkit) even when baseURL is not configured.
 * Override with BASE_URL if needed.
 */
const baseURL = process.env.BASE_URL ||
    'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

async function getVenmoCheckoutButton(page) {
    const venmoFrame = page.frameLocator(
        'iframe[title="PayPal-venmo"].component-frame'
    );

    const venmoButton = venmoFrame.locator(
        '[data-funding-source="venmo"][role="link"]'
    );

    await expect(venmoButton).toBeAttached({
        timeout: 60000
    });

    return venmoButton;
}

test(
    'Signed-in customer can complete a CommerceHub Venmo checkout',
    async ({ page }) => {
        test.setTimeout(300000);

        const magentoEmail = 'jean@pierre.com';
        const magentoPassword = 'Password1';
        const venmoUsername = 'pwv-test-user3';
        const venmoPassword = 'VenmoP@y12345';

        /*
         * Sign in to Magento.
         */
        await page.goto(`${baseURL}/customer/account/login/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        await page.getByRole('textbox', {
            name: 'Email'
        }).fill(magentoEmail);

        await page.getByRole('textbox', {
            name: 'Password',
            exact: true
        }).fill(magentoPassword);

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
            `${baseURL}/chocolate-protein-powder-1.html`,
            {
                waitUntil: 'networkidle'
            }
        );

        const addToCartButton = page.getByRole(
            'button',
            {
                name: 'Add to Cart'
            }
        );

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
         * Navigate directly to checkout.
         */
        await page.goto(`${baseURL}/checkout/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        /*
         * Select first saved shipping address.
         */
        const shippingAddressItems = page.locator(
            '.shipping-address-item'
        );

        await expect(
            shippingAddressItems.first()
        ).toBeVisible({
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
        const shippingNextButton = page.getByRole(
            'button',
            {
                name: 'Next',
                exact: true
            }
        );

        await expect(shippingNextButton).toBeEnabled({
            timeout: 30000
        });

        await shippingNextButton.click();

        /*
         * Select CommerceHub Venmo.
         */
        const venmoPaymentMethod = page.locator('#fiserv_venmo');

        await expect(venmoPaymentMethod).toBeVisible({
            timeout: 60000
        });

        await venmoPaymentMethod.click();

        await expect(venmoPaymentMethod).toBeChecked({
            timeout: 30000
        });

        /*
         * Wait for the SDK-rendered Venmo button.
         */
        const venmoCheckoutButton = await getVenmoCheckoutButton(page);

        /*
         * Listen for the Venmo window before clicking.
         */
        const popupPromise = page.waitForEvent(
            'popup',
            {
                timeout: 60000
            }
        );

        await venmoCheckoutButton.click({
            force: true
        });

        const venmoPopup = await popupPromise;

        /*
         * Wait for the Venmo sign-in page.
         */
        await venmoPopup.waitForURL(
            /id\.venmo\.com\/signin/,
            {
                timeout: 60000
            }
        );

        /*
         * Enter Venmo sandbox username.
         */
        const usernameInput = venmoPopup.locator(
            'input[name="username"], ' +
            'input[name="email"], ' +
            'input[type="email"], ' +
            'input[type="text"]'
        ).first();

        await expect(usernameInput).toBeVisible({
            timeout: 60000
        });

        await usernameInput.fill(venmoUsername);
        await expect(usernameInput).toHaveValue(venmoUsername);

        /*
         * Continue from username entry when a Next or Continue
         * control is available.
         */
        const usernameContinueButton = venmoPopup.getByRole(
            'button',
            {
                name: /Next|Continue/i
            }
        ).first();

        if (await usernameContinueButton.count()) {
            await expect(usernameContinueButton).toBeEnabled({
                timeout: 30000
            });

            await usernameContinueButton.click();
        }

        /*
         * Choose password login instead of one-time-password login.
         */
        const passwordLoginControl = venmoPopup.getByText(
            /Log in with password instead/i
        ).first();

        await expect(passwordLoginControl).toBeVisible({
            timeout: 60000
        });

        await passwordLoginControl.click();

        /*
         * Enter Venmo sandbox password.
         */
        const passwordInput = venmoPopup.locator(
            'input[type="password"], ' +
            'input[name="password"]'
        ).first();

        await expect(passwordInput).toBeVisible({
            timeout: 60000
        });

        await passwordInput.fill(venmoPassword);

        await expect.poll(
            async () => {
                const value = await passwordInput.inputValue();

                return value.length;
            },
            {
                timeout: 10000,
                message: 'Venmo password field remained empty'
            }
        ).toBeGreaterThan(0);

        /*
         * Log in to Venmo.
         */
        const loginButton = venmoPopup.getByRole(
            'button',
            {
                name: /Log In|Login/i
            }
        ).first();

        await expect(loginButton).toBeVisible({
            timeout: 30000
        });

        await expect(loginButton).toBeEnabled({
            timeout: 30000
        });

        await loginButton.click();

        /*
         * Wait for Venmo payment approval page.
         */
        await venmoPopup.waitForURL(
            /account\.venmo\.com\/go\/web\/paypal/,
            {
                timeout: 120000
            }
        );

        const payButton = venmoPopup.getByRole(
            'button',
            {
                name: /^Pay$/i
            }
        ).first();

        await expect(payButton).toBeVisible({
            timeout: 60000
        });

        await expect(payButton).toBeEnabled({
            timeout: 30000
        });

        /*
         * Listen for the Venmo window to close before approving.
         */
        const popupClosePromise = venmoPopup.waitForEvent(
            'close',
            {
                timeout: 120000
            }
        );

        await payButton.click();
        await popupClosePromise;

        /*
         * CommerceHub handles Venmo approval and Magento order placement.
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
