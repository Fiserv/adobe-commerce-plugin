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
					itemType: "PRODUCT",
					itemName: item.name,
					productSKU: item.sku || item.product_sku || null,
					itemDescription: item.name,
					quantity: item.qty,
					amountComponents: {
						unitPrice: parseFloat(item.base_price),
						shippingAmount: parseFloat(item.base_shipping_amount) || 0.00,
						taxAmounts: [
							{
								taxType: "standard",
								taxAmount: parseFloat(item.base_tax_amount)
							}
						]
					}
				};
			});
		}

		function getShippingAddress() {
			const ship = quote.shippingAddress();
			if (!ship) {
				return null;
			}
			const country = ship.country_id || ship.country || null;

			let addr = {
				firstName: ship.firstname || ship.firstName || null,
				lastName: ship.lastname || ship.lastName || null,
				address: {
					street: ship.street && ship.street[0] ? ship.street[0] : null,
					houseNumberOrName: ship.street && ship.street[1] ? ship.street[1] : null,
					city: ship.city || null,
					stateOrProvince: ship.regionCode || ship.region || null,
					postalCode: ship.postcode || null,
					country: country
				},
				phone: {
					phoneNumber: ship.telephone || ship.phone || null
				}
			};

			return addr;
		}

        function getMerchantDetails()
        {
			const cfg = window.checkoutConfig && window.checkoutConfig.payment && window.checkoutConfig.payment.fiserv_affirm ? window.checkoutConfig.payment.fiserv_affirm : {};
			return {
				merchantId: cfg.merchantId || window.checkoutConfig.merchantId || '',
				terminalId: cfg.terminalId || cfg.terminalid || ''
			};
        }


		function buildPayload()
		{
            let orderData = getOrderData();
            let shippingAddress = getShippingAddress();
            let merchantDetails = getMerchantDetails();

			return {
				orderData: orderData,
                shippingAddress: shippingAddress,
                merchantDetails: merchantDetails
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
