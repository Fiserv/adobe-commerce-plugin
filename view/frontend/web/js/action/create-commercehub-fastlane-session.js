define(
	[
        'Fiserv_Payments/js/action/create-commercehub-session',
		'Fiserv_Payments/js/model/locale',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote',
		'mage/translate'
	],
	function (
		session,
		localeModel,
		customer,
		quote,
		$t
	) {
		'use strict';

		function getBaseCurrencyCode(totals)
		{
			return (totals && totals.base_currency_code)
				|| window.checkoutConfig?.quoteData?.base_currency_code
				|| window.checkoutConfig?.totalsData?.base_currency_code
				|| '';
		}

		function getAmount()
		{
			let totals = quote.totals();
			let rawGrandTotal = totals ? totals.grand_total : 0;
			let grandTotal = Math.round(rawGrandTotal * 100) / 100;
			let currency = getBaseCurrencyCode(totals);

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
				"amount" : amount,
				"locale" : localeModel.current()
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
				throw new Error($t('An error occurred while creating Commercehub payment session for APMs.'));
			}
		};
	}
);
