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

		function getBillingAddress() {
			// billing address can be null and is not strictly required for all sessions
			if (quote.billingAddress() === null) {
				return;
			}

			const addr = {
				city: quote.billingAddress().city,
				stateOrProvince: quote.billingAddress().region,
				postalCode: quote.billingAddress().postcode,
				country: quote.billingAddress().country_id,
			};

			if (quote.billingAddress().street[0]) {
				addr.street = quote.billingAddress().street[0];
			}
			if (quote.billingAddress().street[1]) {
				addr.houseNumberOrName = quote.billingAddress().street[1];
			}

			return addr;
		}

		function getCustomer() {
			const email = customer.isLoggedIn() ? customer.customerData.email : quote.guestEmail;
			const firstName = customer.isLoggedIn() ? customer.customerData.firstname : quote.billingAddress()?.firstname;
			const lastName = customer.isLoggedIn() ? customer.customerData.lastname : quote.billingAddress()?.lastname;
			const id = customer.isLoggedIn() ? customer.customerData.id : 'guest';

			return {
				id,
				firstName,
				lastName,
				email,
			};
		}

		function getAmount() {
			const rawGrandTotal = quote.totals() ? quote.totals().grand_total : 0;
			const grandTotal = Math.round(rawGrandTotal * 100) / 100;
			const currency = quote.totals() ? quote.totals().quote_currency_code : 'USD';

			return {
				total: grandTotal,
				currency,
			};
		}

		function buildPayload() {
			const amount = getAmount();
			const customer = getCustomer();
			const billingAddress = getBillingAddress();

			return {
				amount,
				customer,
				billingAddress,
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
