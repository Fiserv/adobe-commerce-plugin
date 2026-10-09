const { test, expect } = require('@playwright/test');

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
    'Signed-in customer can complete a CommerceHub credit card checkout',
    async ({ page }) => {
        test.setTimeout(240000);

        const email = 'jean@pierre.com';
        const password = 'Password1';

        /*
         * Sign in.
         */
        await page.goto('/customer/account/login/');

        await page.getByRole('textbox', {
            name: 'Email'
        }).fill(email);

        await page.getByRole('textbox', {
            name: 'Password',
            exact: true
        }).fill(password);

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
            page.getByText(
                /added .* to your shopping cart/i
            )
        ).toBeVisible({
            timeout: 30000
        });

        /*
         * Product is now in the cart.
         * Navigate directly into checkout.
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

        const firstShippingAddress =
            shippingAddressItems.first();

        const addressIsSelected =
            await firstShippingAddress.evaluate(
                element => element.classList.contains(
                    'selected-item'
                )
            );

        if (!addressIsSelected) {
            const shipHereButton =
                firstShippingAddress.getByRole(
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
        await expect(
            firstShippingAddress
        ).toContainText(
            'Jean Pierre'
        );

        await expect(
            firstShippingAddress
        ).toContainText(
            '100 Connell Drive'
        );

        await expect(
            firstShippingAddress
        ).toContainText(
            /Berkley Heights/i
        );

        await expect(
            firstShippingAddress
        ).toContainText(
            '07922'
        );

        await expect(
            firstShippingAddress
        ).toContainText(
            '9089089081'
        );

        /*
         * Select first available shipping method.
         */
        const shippingMethods = page.locator(
            '#checkout-shipping-method-load ' +
            'input[type="radio"]'
        );

        await expect(
            shippingMethods.first()
        ).toBeVisible({
            timeout: 60000
        });

        const checkedShippingMethod = page.locator(
            '#checkout-shipping-method-load ' +
            'input[type="radio"\]:checked'
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

        await expect(
            shippingNextButton
        ).toBeEnabled({
            timeout: 30000
        });

        await shippingNextButton.click();

        /*
         * Activate CommerceHub Credit/Debit Card.
         */
        const commerceHubPaymentMethod =
            page.getByRole(
                'radio',
                {
                    name: 'Credit/Debit Card',
                    exact: true
                }
            );

        await expect(
            commerceHubPaymentMethod
        ).toBeVisible({
            timeout: 60000
        });

        await commerceHubPaymentMethod.click();

        await expect(
            commerceHubPaymentMethod
        ).toBeChecked({
            timeout: 30000
        });

        /*
         * Wait for Fiserv to mount the hosted
         * Card Number iframe.
         */
        const cardNumberContainer = page.locator(
            '#fiserv_commercehub-card-number'
        );

        await expect(
            cardNumberContainer
        ).toBeVisible({
            timeout: 60000
        });

        const cardNumberIframe =
            cardNumberContainer.locator('iframe');

        await expect(
            cardNumberIframe
        ).toBeAttached({
            timeout: 60000
        });

        /*
         * Enter Card Number.
         */
        await fillHostedField(
            page,
            '#fiserv_commercehub-card-number',
            '4111111111111111'
        );

        /*
         * Enter Name on Card.
         */
        await fillHostedField(
            page,
            '#fiserv_commercehub-name-on-card',
            'Jean Pierre'
        );

        /*
         * Enter Security Code.
         */
        await fillHostedField(
            page,
            '#fiserv_commercehub-security-code',
            '111'
        );

        /*
         * Select December as expiration month.
         *
         * This select lives inside the Fiserv-hosted
         * expiration-month iframe.
         */
        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-month',
            '#expirationMonth',
            '12'
        );

        /*
         * Select 2032 as expiration year.
         *
         * This select lives inside the Fiserv-hosted
         * expiration-year iframe.
         */
        await selectHostedOption(
            page,
            '#fiserv_commercehub-expiration-year',
            '#expirationYear',
            '2032'
        );

        /*
         * CommerceHub enables Place Order after
         * the complete SDC payment form becomes valid.
         */
        const placeOrderButton = page.getByRole(
            'button',
            {
                name: 'Place Order',
                exact: true
            }
        );

        await expect(
            placeOrderButton
        ).toBeEnabled({
            timeout: 60000
        });

        /*
         * Submit CommerceHub payment and
         * place the Magento order.
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
            page.getByText(
                /thank you for your purchase/i
            )
        ).toBeVisible({
            timeout: 30000
        });
    }
);