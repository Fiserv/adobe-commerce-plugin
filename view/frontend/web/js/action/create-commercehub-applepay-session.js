define(
	[
        'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Magento_Checkout/js/model/quote',
	],
	function (
		session,
		quote,
	) {
		'use strict';
		
        function getOrderData() {
			const quoteItems = quote.getItems() || [];
			const items = quoteItems.map((item, index) => {
                return {
                    itemNumber: index + 1,
                    itemName: item.name,
                    itemDescription: item.name,
                    quantity: item.qty,
                    amountComponents: {
                        unitPrice: parseFloat(item.base_price),
                        shippingAmount: 0.00,
                        taxAmounts: [
                            {
                                taxType: "standard",
                                taxAmount: parseFloat(item.base_tax_amount)
                            }
                        ]
                    }
                };
            });

            const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);
            return { itemDetails: items, itemCount: itemCount };
        }

		function getCountry()
		{
			const billingAddress = quote.billingAddress();
			if (billingAddress)
			{
				return billingAddress.country_id || billingAddress.countryId || "US";
			}

			const shippingAddress = quote.shippingAddress();
			if (shippingAddress)
			{
				return shippingAddress.country_id || shippingAddress.countryId || "US";
			}

			return "US";
		}

		function buildPayload()
		{
            let orderData = getOrderData();
            let country = getCountry();

			return {
				orderData: orderData,
				country: country
			};
		}

		return async function (params) {
			let payload = buildPayload();
			if (params)
			{
				payload = { ...payload, ...params };
			}

			try {
				return await session(payload);
			} catch (error) {
				const details = error instanceof Error && error.message ? ` ${error.message}` : "";
				throw new Error(`An error occurred while creating Commercehub Apple Pay payment session.${details}`);
			}
		};
	}
);
