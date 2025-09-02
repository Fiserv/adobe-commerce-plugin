define(
    [
        'jquery',
        'mage/url',
		'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
		'Magento_Catalog/js/price-utils'
    ],
    function (
        $,
        urlBuilder,
        customer,
        quote,
        priceUtils,
    ) {
        'use strict';
		
		function getBillingAddress()
		{
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
			let firstName = customer.isLoggedIn() ? customer.customerData.firstname : quote.billingAddress().firstname;
			let lastName = customer.isLoggedIn() ? customer.customerData.lastname : quote.billingAddress().lastname;
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
			let grandTotal = quote.totals() ? quote.totals().grand_total : 0;
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
				"billingAddress" : billingAddress
			};
		}

        return async function (params) {
            let serviceUrl = 'fiserv/paypal/getcredentials'
			let payload = buildPayload();
			if (params)
			{
				payload = { ...params, ...payload };
			}

			try {
				let response = await fetch(urlBuilder.build(serviceUrl), {
					method: 'POST',
					headers: { 
						'Content-Type': 'application/json',
						'X-Requested-With' : 'XMLHttpRequest'	
					},
					body: JSON.stringify(payload),
					credentials: 'same-origin'
				});
				
				if (!response.ok)
				{
					throw new Error("Credentials request failure");
				}
				return await response.json();
			} catch (error) {
				throw new Error("An error occurred while beginning Commercehub payment session.");
			}
        };
    }
);
