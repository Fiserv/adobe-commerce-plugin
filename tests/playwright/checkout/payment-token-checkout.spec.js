const { test, expect } = require('@playwright/test');

test(
    'Signed-in customer can complete a CommerceHub payment token checkout',
    async ({ page }) => {
        test.setTimeout(240000);

        const magentoEmail = 'jean@pierre.com';
        const magentoPassword = 'Password1';

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
         * Open product.
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
         * Select the specific CommerceHub stored payment token:
         * ending 0042, expires 05/2035.
         */
        const paymentTokenRadio = page.locator(
            '#fiserv_commercehub_vault_15'
        );

        await expect(paymentTokenRadio).toBeVisible({
            timeout: 60000
        });

        await paymentTokenRadio.click();

        await expect(paymentTokenRadio).toBeChecked({
            timeout: 30000
        });

        /*
         * Verify the selected token is the expected stored card.
         */
        const paymentTokenLabel = page.locator(
            'label[for="fiserv_commercehub_vault_15"]'
        );

        await expect(paymentTokenLabel).toBeVisible({
            timeout: 30000
        });

        await expect(paymentTokenLabel).toContainText(
            '************0042'
        );

        await expect(paymentTokenLabel).toContainText(
            '05/2035'
        );

        /*
         * Wait for the selected stored-card payment method to expose
         * an enabled Place Order button.
         */
        const placeOrderButton = page.locator(
            '#fiserv_commercehub_vault_15-place-order'
        );

        await expect(placeOrderButton).toBeEnabled({
            timeout: 60000
        });

        /*
         * Submit the Magento order using the selected CommerceHub token.
         */
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
    }
);
