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
            const items = quote.getItems().map((item, index) => {
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

		function buildPayload()
		{
            let orderData = getOrderData();

			return {
				orderData: orderData,
                country: "US"
			};
		}

		return async function (params) {
			let payload = buildPayload();
			if (params)
			{
				payload = { ...params, ...payload };
			}

			try {
				return await session(payload);
			} catch (error) {
				throw new Error("An error occurred while creating Commercehub Apple Pay payment session.");
			}
		};
	}
);
