define(
	[
        'Fiserv_Payments/js/action/create-commercehub-session',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote'
	],
	function (
		session,
		customer,
		quote,
	) {
		'use strict';

		function getAmount()
		{
			let rawGrandTotal = quote.totals() ? quote.totals().grand_total : 0;
			let grandTotal = Math.round(rawGrandTotal * 100) / 100;
			let currency = quote.totals() ? quote.totals().quote_currency_code : "USD";
			
			// don't look at this
			window.checkoutConfig.payment.fiserv_paypal_fastlane.initializedAmount = grandTotal;
			
			return {
				"total" : grandTotal,
				"currency" : currency
			};
		}

		function buildPayload()
		{
			let amount = getAmount();
			return {
				"amount" : amount
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
				throw new Error("An error occurred while creating Commercehub payment session for APMs.");
			}
		};
	}
);
