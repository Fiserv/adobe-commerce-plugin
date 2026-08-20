define(
	[
        'Fiserv_Payments/js/action/create-commercehub-session',
		'Magento_Customer/js/model/customer',
		'Magento_Checkout/js/model/quote',
		'mage/translate'
	],
	function (
		session,
		customer,
		quote,
		$t
	) {
		'use strict';
		
		function getBillingAddress()
		{
			// billing address can be null and is not strictly required for all sessions
			if (quote.billingAddress() === null)
			{
				return;
			}

			let addr = {
				"city" : quote.billingAddress().city,
				"stateOrProvince" : quote.billingAddress().region,
				"postalCode" : quote.billingAddress().postcode,
				"country" : quote.billingAddress().country_id
			};

			if (quote.billingAddress().street[0])
			{
				addr["street"] = quote.billingAddress().street[0];
			}
			if (quote.billingAddress().street[1])
			{
				addr["houseNumberOrName"] = quote.billingAddress().street[1];
			}

			return addr;
		}

		function getCustomer()
		{
			let email = customer.isLoggedIn() ? customer.customerData.email : quote.guestEmail;
			let firstName = customer.isLoggedIn() ? customer.customerData.firstname : quote.billingAddress()?.firstname;
			let lastName = customer.isLoggedIn() ? customer.customerData.lastname : quote.billingAddress()?.lastname;
			let id = customer.isLoggedIn() ? customer.customerData.id : "guest";
			let locale = getLocale();
			return {
				"id" : id,
				"firstName" : firstName,
				"lastName" : lastName,
				"email" : email,
				"locale" : locale
			};
		}

		function getLocale()
		{
			// IETF BCP47 (e.g. "en-US") locale of the current storefront/session,
			// used by Commerce Hub to localize wallet UIs (e.g. Apple Pay button text).
			let locale = document.documentElement.lang || (navigator.language || navigator.userLanguage);
			return locale ? locale.replace('_', '-') : 'en-US';
		}

		function getAmount()
		{
			const totals = quote.totals();
			// grand_total already reflects all applied discounts, store credit, rewards, and gift cards,
			// so it represents the actual amount to be charged to the credit card.
			let grandTotal = totals ? parseFloat(totals.grand_total) || 0 : 0;
			const currency = totals ? (totals.quote_currency_code || 'USD') : 'USD';


			grandTotal = Math.max(0, Math.round(grandTotal * 100) / 100);
			return {
				"total" : grandTotal,
				"currency" : currency
			};
		}

		function buildPayload()
		{
			let amount = getAmount();
			let customer = getCustomer();
			let billingAddress = getBillingAddress();
			return {
				"amount" : amount,
				"customer" : customer,
				"billingAddress" : billingAddress,
				"country" : "US"
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
				throw new Error($t('An error occurred while creating Commercehub payment session for APMs.'));
			}
		};
	}
);
