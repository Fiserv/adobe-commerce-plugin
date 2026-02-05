define(
	[
		'Fiserv_Payments/js/action/create-commercehub-session',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote',
	],
	(
		session,
		customer,
		quote,
	) => {
		'use strict';

		function getAmount() {
			const rawGrandTotal = quote.totals() ? quote.totals().grand_total : 0;
			const grandTotal = Math.round(rawGrandTotal * 100) / 100;
			const currency = quote.totals() ? quote.totals().quote_currency_code : 'USD';

			// don't look at this
			globalThis.checkoutConfig.payment.fiserv_paypal_fastlane.initializedAmount = grandTotal;

			return {
				total: grandTotal,
				currency,
			};
		}

		function buildPayload() {
			const amount = getAmount();

			return {
				amount,
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
				throw new Error('An error occurred while creating Commercehub payment session for APMs.');
			}
		};
	},
);
