const { test, expect } = require('@playwright/test');

/*
 * Use an absolute storefront URL so the test works in every project
 * (chromium, firefox, webkit) even when baseURL is not configured.
 * Override with BASE_URL if needed.
 */
const baseURL = process.env.BASE_URL ||
    'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

async function findPayPalCheckoutButton(page) {
    await expect.poll(
        async () => {
            for (const frame of page.frames()) {
                const button = frame.getByRole('link', {
                    name: 'PayPal Checkout',
                    exact: true
                });

                if (await button.count()) {
                    return true;
                }
            }

            return false;
        },
        {
            timeout: 60000,
            message: 'Waiting for PayPal Checkout component'
        }
    ).toBe(true);

    for (const frame of page.frames()) {
        const button = frame.getByRole('link', {
            name: 'PayPal Checkout',
            exact: true
        });

        if (await button.count()) {
            await expect(button).toBeVisible({
                timeout: 30000
            });

            return button;
        }
    }

    throw new Error('PayPal Checkout button was not found.');
}

test(
    'Signed-in customer can complete a CommerceHub PayPal checkout',
    async ({ page }) => {
        test.setTimeout(300000);

        const magentoEmail = 'jean@pierre.com';
        const magentoPassword = 'Password1';
        const paypalEmail = 'parul@fiserv.com';
        const paypalPassword = '!@fp99043%%3';

        /*
         * Sign in.
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
         * Open product and add to cart.
         */
        await page.goto(
            `${baseURL}/chocolate-protein-powder-1.html`,
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
         * Select CommerceHub PayPal.
         */
        const paypalPaymentMethod = page.locator('#fiserv_paypal');

        await expect(paypalPaymentMethod).toBeVisible({
            timeout: 60000
        });

        await paypalPaymentMethod.click();

        await expect(paypalPaymentMethod).toBeChecked({
            timeout: 30000
        });

        await expect(page.locator('#paypal-button-container')).toBeVisible({
            timeout: 60000
        });

        /*
         * Click PayPal Checkout and capture the PayPal popup window.
         */
        const paypalCheckoutButton = await findPayPalCheckoutButton(page);

        const popupPromise = page.waitForEvent('popup', {
            timeout: 60000
        });

        await paypalCheckoutButton.click();

        const paypalPopup = await popupPromise;

        await paypalPopup.waitForLoadState('domcontentloaded');

        /*
         * PayPal login: email.
         */
        const paypalEmailInput = paypalPopup.locator(
            'input[type="email"], input[name="login_email"], #email'
        ).first();

        await expect(paypalEmailInput).toBeVisible({
            timeout: 60000
        });

        await paypalEmailInput.fill(paypalEmail);
        await expect(paypalEmailInput).toHaveValue(paypalEmail);

        const nextButton = paypalPopup.getByRole('button', {
            name: 'Next',
            exact: true
        });

        await expect(nextButton).toBeEnabled({
            timeout: 30000
        });

        await nextButton.click();

        await expect(paypalEmailInput).toBeHidden({
            timeout: 60000
        });

        /*
         * PayPal login: password.
         */
        const paypalPasswordInput = paypalPopup.locator(
            'input[type="password"], input[name="login_password"], #password'
        ).first();

        await expect(paypalPasswordInput).toBeVisible({
            timeout: 60000
        });

        await paypalPasswordInput.clear();
        await paypalPasswordInput.pressSequentially(paypalPassword, {
            delay: 50
        });

        await expect.poll(
            async () => (await paypalPasswordInput.inputValue()).length,
            {
                timeout: 10000,
                message: 'PayPal password field remained empty'
            }
        ).toBeGreaterThan(0);

        const loginButton = paypalPopup.getByRole('button', {
            name: 'Log In',
            exact: true
        });

        await expect(loginButton).toBeEnabled({
            timeout: 30000
        });

        await loginButton.click();

        /*
         * Approve the payment. The popup closes and Magento places
         * the order.
         */
        const approvalButton = paypalPopup.locator('#one-time-cta');

        await expect(approvalButton).toBeVisible({
            timeout: 60000
        });

        await expect(approvalButton).toBeEnabled({
            timeout: 30000
        });

        await expect(approvalButton).toHaveText(/^\s*Pay\s*$/);

        const popupClosePromise = paypalPopup.waitForEvent('close', {
            timeout: 120000
        });

        await approvalButton.click();
        await popupClosePromise;

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
