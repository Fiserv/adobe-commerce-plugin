define([
	'jquery',
	'uiComponent',
	'ko',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-session',
	'Fiserv_Payments/js/action/modify-requirejs',
	'chBraintreeClient',
	'ch-braintree-hosted-fields',
	'Magento_Customer/js/model/customer',
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/model/new-customer-address',
	'Magento_Checkout/js/action/select-billing-address'
], function (
	$,
	Component,
	ko,
	chAdapter,
	chSession,
	modifyRequirejs,
	btClient,
	btHostedFields,
	customerModel,
	fullScreenLoader,
	quote,
	newAddress,
	selectBillingAddress
) {
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Fiserv_Payments/payment/paypal/email'
		},
		initialize: async function () {
			this._super();

			let maps = {
				'braintree/client.min' : 'chBraintreeClient',
				'braintree/hosted-fields.min' : 'ch-braintree-hosted-fields'
			};
			modifyRequirejs( maps );
			let credsResponse = undefined;
			var self = this;
			try {
				credsResponse = await chSession( { "threeDSecure" : false } );
			} catch (error) {
				console.log("An error occurred while starting Commercehub payment session: ".concat(error));
				return;
			}

			let credentials = credsResponse["ch_credentials"];

			await chAdapter.initSdk(
				window.checkoutConfig.payment['fiserv_commercehub'],
				credentials
			);

			const paypal = await window.fiserv.components.paypal();

			window.braintree = window.braintree || {};
			window.braintree.client = btClient;
			window.braintree.hostedFields = btHostedFields;

			const fastlane = await paypal.fastlane();

			const customer = $("#customer");
			const consent = $("#consent");

			try {
				await fastlane.renderWatermark("fiserv-paypal-watermark-container");
				customer.css("visibility", "visible");
				consent.css("visibility", "visible");
			} catch (error) {
				console.error("Watermark rendering failed:", error);
			}

			const fastlaneEmailSubmitButton = $("#email-submit-button");

			fastlaneEmailSubmitButton.on("click", async function () {
				const emailInputValue = $("#email-input").val();

				try {
					const authenticateResult = await fastlane.authenticate({
						email: emailInputValue
					});

					if( ! authenticateResult.isGuestCheckout) {
						const symbolKey = Object.getOwnPropertySymbols(authenticateResult)[0];
						const symbolData = authenticateResult[symbolKey];
						console.log(symbolData.customerContextId)
						console.log(authenticateResult);

						const profileData = symbolData.profile;

						const magentoAddress = {
							firstname: profileData.shippingAddress.name.firstName,
							lastname: profileData.shippingAddress.name.lastName,
							street: [profileData.shippingAddress.address.addressLine1, profileData.shippingAddress.address.addressLine2],
							city: profileData.shippingAddress.address.adminArea2,
							region: profileData.shippingAddress.address.adminArea1,
							postcode: profileData.shippingAddress.address.postalCode,
							countryId: profileData.shippingAddress.address.countryCode,
							telephone: profileData.shippingAddress.phoneNumber.nationalNumber
						};

						const newBillingAddress = newAddress(magentoAddress);

						selectBillingAddress(newBillingAddress);
					}
				} catch (error) {
					console.error("Authentication failed:", error);
				}
			});

			return this;
		},
		isCustomerLoggedIn: function() {
			return customerModel.isLoggedIn();
		}
	});
});
