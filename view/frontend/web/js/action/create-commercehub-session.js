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

        function getOrderData() {
            const items = quote.getItems().map((item, index) => {
                return {
                    itemNumber: index + 1,
                    itemType: "Protein Product",
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
            return {itemDetails: items, itemCount: itemCount};
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

        function buildPayload() {
            let amount = getAmount();
            let customer = getCustomer();
            let billingAddress = getBillingAddress();
            let orderData = getOrderData();

            return {
                amount: amount,
                customer: customer,
                billingAddress: billingAddress,
                orderData: orderData,
                //Add dynamicDescriptors block
                dynamicDescriptors: {
                    merchantName: "Virtual Shop",
                    address: {
                        country: "US"
                    }
                },
                //Add additionalDataCommon block with custom ecomURL
                additionalDataCommon: {
                    additionalData: {
                        ecomURL: "merchant.com"
                    }
                }
            };
        }

        return async function (params) {
            let serviceUrl = 'fiserv/commercehub/getcredentials'
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
