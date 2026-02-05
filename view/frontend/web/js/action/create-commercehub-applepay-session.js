define(
	[
		'Fiserv_Payments/js/action/create-commercehub-enriched-session',
		'Magento_Checkout/js/model/quote',
	],
	(
		session,
		quote,
	) => {
		'use strict';

		function getOrderData() {
			const items = quote.getItems().map((item, index) => ({
				itemNumber: index + 1,
				itemName: item.name,
				itemDescription: item.name,
				quantity: item.qty,
				amountComponents: {
					unitPrice: Number.parseFloat(item.base_price),
					shippingAmount: 0,
					taxAmounts: [
						{
							taxType: 'standard',
							taxAmount: Number.parseFloat(item.base_tax_amount),
						},
					],
				},
			}));

			const itemCount = items.reduce((sum, item) => sum + item.quantity, 0);

			return { itemDetails: items, itemCount };
		}

		function buildPayload() {
			const orderData = getOrderData();

			return {
				orderData,
				country: 'US',
			};
		}

		return async function (parameters) {
			let payload = buildPayload();

			if (parameters) {
				payload = { ...parameters, ...payload };
			}

			try {
				return await session(payload);
			} catch {
				throw new Error('An error occurred while creating Commercehub Apple Pay payment session.');
			}
		};
	},
);
