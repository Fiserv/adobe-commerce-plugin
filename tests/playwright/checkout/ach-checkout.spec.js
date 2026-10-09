const { test, expect } = require('@playwright/test');

async function getHostedControl(page, containerSelector, controlSelector) {
    const container = page.locator(containerSelector);

    await expect(container).toBeVisible({ timeout: 60000 });

    const iframe = container.locator('iframe');
    await expect(iframe).toBeAttached({ timeout: 60000 });

    const frame = iframe.contentFrame();
    const control = frame.locator(controlSelector);

    await expect(control).toBeVisible({ timeout: 60000 });

    return control;
}

async function selectHostedOption(page, containerSelector, selectSelector, value) {
    const select = await getHostedControl(
        page,
        containerSelector,
        selectSelector
    );

    await select.selectOption(value);
    await expect(select).toHaveValue(value);
}

async function fillHostedField(page, containerSelector, inputSelector, value) {
    const input = await getHostedControl(
        page,
        containerSelector,
        inputSelector
    );

    await input.fill(value);

    /*
     * Fiserv SDC can immediately mask sensitive ACH fields after entry.
     * When that happens, the displayed value changes while the original
     * entered value is retained in data-original.
     */
    await expect.poll(
        async () => {
            const displayedValue = await input.inputValue();
            const originalValue = await input.getAttribute('data-original');

            return displayedValue === value || originalValue === value;
        },
        {
            timeout: 10000,
            message: `Hosted field ${inputSelector} did not retain the entered value`
        }
    ).toBe(true);
}

test(
    'Signed-in customer can complete a CommerceHub ACH checkout',
    async ({ page }) => {
        test.setTimeout(300000);

        const magentoEmail = 'jean@pierre.com';
        const magentoPassword = 'Password1';
        const baseURL = process.env.BASE_URL ||
            'https://adobe-commerce-dev-tyson.commercehub-nonprod.aws.fisv.cloud';

        await page.goto(`${baseURL}/customer/account/login/`);

        await page.getByRole('textbox', { name: 'Email' }).fill(magentoEmail);
        await page.getByRole('textbox', {
            name: 'Password',
            exact: true
        }).fill(magentoPassword);

        await page.getByRole('button', {
            name: 'Sign In',
            exact: true
        }).click();

        await expect(page).not.toHaveURL(/customer\/account\/login/, {
            timeout: 30000
        });

        await page.goto(`${baseURL}/chocolate-protein-powder-1.html`, {
            waitUntil: 'networkidle'
        });

        const addToCartButton = page.getByRole('button', {
            name: 'Add to Cart'
        });

        await expect(addToCartButton).toBeEnabled({ timeout: 30000 });
        await addToCartButton.click();

        await expect(
            page.getByText(/added .* to your shopping cart/i)
        ).toBeVisible({ timeout: 30000 });

        await page.goto(`${baseURL}/checkout/`, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });

        const shippingAddressItems = page.locator('.shipping-address-item');
        await expect(shippingAddressItems.first()).toBeVisible({
            timeout: 60000
        });

        const firstShippingAddress = shippingAddressItems.first();
        const addressIsSelected = await firstShippingAddress.evaluate(
            element => element.classList.contains('selected-item')
        );

        if (!addressIsSelected) {
            const shipHereButton = firstShippingAddress.getByRole('button', {
                name: /Ship Here/i
            });

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

        const shippingMethods = page.locator(
            '#checkout-shipping-method-load input[type="radio"]'
        );

        await expect(shippingMethods.first()).toBeVisible({ timeout: 60000 });

        const checkedShippingMethod = page.locator(
            '#checkout-shipping-method-load input[type="radio"]:checked'
        );

        if (!(await checkedShippingMethod.count())) {
            await shippingMethods.first().check();
        }

        const shippingNextButton = page.getByRole('button', {
            name: 'Next',
            exact: true
        });

        await expect(shippingNextButton).toBeEnabled({ timeout: 30000 });
        await shippingNextButton.click();

        const achPaymentMethod = page.locator('#fiserv_ach');
        await expect(achPaymentMethod).toBeVisible({ timeout: 60000 });
        await achPaymentMethod.click();
        await expect(achPaymentMethod).toBeChecked({ timeout: 30000 });

        await selectHostedOption(
            page,
            '#fiserv_ach-account-type',
            '#accountType',
            'SAVINGS'
        );

        await selectHostedOption(
            page,
            '#fiserv_ach-check-type',
            '#checkType',
            'PERSONAL'
        );

        /*
         * PERSONAL check type does not require Business Name.
         * Skip #fiserv_ach-business-name and continue directly
         * to Account Number.
         */
        await fillHostedField(
            page,
            '#fiserv_ach-account-number',
            '#accountNumber',
            '10224897'
        );

        await fillHostedField(
            page,
            '#fiserv_ach-routing-number',
            '#routingNumber',
            '122000496'
        );

        await selectHostedOption(
            page,
            '#fiserv_ach-id-type',
            '#idType',
            'Driver License'
        );

        await selectHostedOption(
            page,
            '#fiserv_ach-driver-license-state',
            '#driverLicenseState',
            'TX'
        );

        await fillHostedField(
            page,
            '#fiserv_ach-id-value',
            '#idValue',
            '12340981'
        );

        const submitButton = page.locator('#fiserv-ach-checkout-submit');

        await expect(submitButton).toBeVisible({ timeout: 60000 });
        await expect(submitButton).toBeEnabled({ timeout: 60000 });
        await submitButton.click();

        const authorizationDialog = page.getByRole('dialog');
        await expect(authorizationDialog).toBeVisible({ timeout: 60000 });

        const okButton = authorizationDialog.getByRole('button', {
            name: /OK/i
        });

        await expect(okButton).toBeVisible({ timeout: 30000 });
        await okButton.click();

        await expect(page).toHaveURL(
            /\/checkout\/onepage\/success\/?/,
            {
                timeout: 120000
            }
        );

        await expect(
            page.getByText(/thank you for your purchase/i)
        ).toBeVisible({ timeout: 30000 });
    }
);
