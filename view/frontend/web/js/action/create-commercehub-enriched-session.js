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
			return {
				"id" : id,
				"firstName" : firstName,
				"lastName" : lastName,
				"email" : email
			};
		}

		function getAmount()
		{
			let rawGrandTotal = quote.totals() ? quote.totals().grand_total : 0;
			let grandTotal = Math.round(rawGrandTotal * 100) / 100;
			let currency = quote.totals() ? quote.totals().quote_currency_code : "USD";
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
				throw new Error("An error occurred while creating Commercehub payment session for APMs.");
			}
		};
	}
);
