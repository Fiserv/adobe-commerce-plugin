const { test, expect } = require('@playwright/test');

test(
    'Signed-in customer can complete a CommerceHub Google Pay checkout',
    async ({ page, context }) => {
        test.setTimeout(300000);

        const magentoEmail = 'jean@pierre.com';
        const magentoPassword = 'Password1';
        const googleEmail = 'muruga.gpay123@gmail.com';
        const googlePassword = 'Murugagpay1234!';

        /*
         * Sign in to Magento.
         */
        await page.goto('/customer/account/login/');

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
         * Open product and add it to the cart.
         */
        await page.goto(
            '/chocolate-protein-powder-1.html',
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
        await page.goto('/checkout/', {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        /*
         * Select the first saved shipping address.
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
         * Select the first available shipping method.
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
         * Select CommerceHub Google Pay.
         */
        const googlePayPaymentMethod = page.locator(
            '#fiserv_googlepay'
        );

        await expect(googlePayPaymentMethod).toBeVisible({
            timeout: 60000
        });

        await googlePayPaymentMethod.click();

        await expect(googlePayPaymentMethod).toBeChecked({
            timeout: 30000
        });

        /*
         * commercehub-googlepay.js mounts the SDK button here.
         */
        const googlePayContainer = page.locator(
            '#googlepay-button-container'
        );

        await expect(googlePayContainer).toBeVisible({
            timeout: 60000
        });

        const googlePayButton = googlePayContainer.locator(
            '.gpay-button'
        );

        await expect(googlePayButton).toBeVisible({
            timeout: 60000
        });

        /*
         * Google Pay opens its checkout UI in a new page/window.
         * Listen before clicking so the new page cannot be missed.
         */
        const googlePayPagePromise = context.waitForEvent(
            'page',
            {
                timeout: 60000
            }
        );

        await googlePayButton.click();

        const googlePayPage = await googlePayPagePromise;

        /*
         * Google account email page.
         */
        await googlePayPage.waitForURL(
            /accounts\.google\.com\/.*signin\/identifier/,
            {
                timeout: 60000
            }
        );

        /*
         * Enter the Google account email.
         *
         * The Google identifier page renders:
         *
         * <input id="identifierId" name="identifier"
         *        aria-label="Email or phone" type="text">
         */
        const googleEmailInput = googlePayPage.locator(
            '#identifierId'
        );

        await expect(googleEmailInput).toBeVisible({
            timeout: 60000
        });

        await googleEmailInput.click();
        await googleEmailInput.fill(googleEmail);

        await expect(googleEmailInput).toHaveValue(
            googleEmail
        );

        /*
         * Continue to the Google password page.
         */
        const emailNextButton = googlePayPage.locator(
            '#identifierNext'
        );

        await expect(emailNextButton).toBeVisible({
            timeout: 30000
        });

        await expect(emailNextButton).toBeEnabled({
            timeout: 30000
        });

        await emailNextButton.click();

        const googlePasswordInput = googlePayPage.locator(
            'input[type="password"]'
        ).first();

        await expect(googlePasswordInput).toBeVisible({
            timeout: 60000
        });

        await googlePasswordInput.fill(googlePassword);

        await expect.poll(
            async () => {
                const value = await googlePasswordInput.inputValue();

                return value.length;
            },
            {
                timeout: 10000,
                message: 'Google password field remained empty'
            }
        ).toBeGreaterThan(0);

        const passwordNextButton = googlePayPage.getByRole(
            'button',
            {
                name: /Next/i
            }
        ).first();

        await expect(passwordNextButton).toBeEnabled({
            timeout: 30000
        });

        await passwordNextButton.click();

        /*
         * Wait for the Google Pay review page.
         */
        await googlePayPage.waitForURL(
            /pay\.google\.com/,
            {
                timeout: 120000
            }
        );

        const payButton = googlePayPage.getByRole(
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
         * Start observing either Google Pay UI closure or Magento
         * success before approving the payment.
         */
        const googlePayClosePromise = googlePayPage.waitForEvent(
            'close',
            {
                timeout: 120000
            }
        ).catch(() => null);

        await payButton.click();

        await googlePayClosePromise;

        /*
         * commercehub-googlepay.js handles onApprove and invokes
         * placeOrder('parent').
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
