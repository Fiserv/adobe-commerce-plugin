define([
	'jquery',
	'Fiserv_Payments/js/ch-adapter',
	'Fiserv_Payments/js/action/create-commercehub-session',
	'chBraintreeClient',
	'ch-braintree-hosted-fields',
	'Magento_Checkout/js/model/new-customer-address',
	'Magento_Checkout/js/action/select-billing-address'
], function (
	$,
	chAdapter,
	chSession,
	chBraintreeClient,
	chBraintreeHostedFields,
	newAddress,
	selectBillingAddress
) {
	'use strict';

	return {
		fastlane: undefined,

		fastlaneInit: async function() {

			let credsResponse = undefined;

			var self = this;
			try {
				credsResponse = await chSession();
			} catch (error) {
				console.log("An error occurred while starting Commercehub payment session: ".concat(error));
				return;
			}

			let credentials =credsResponse["ch_credentials"];

			await chAdapter.initSdk(
				window.checkoutConfig.payment['fiserv_commercehub'],
				credentials
			);

			const paypal = await window.fiserv.components.paypal();

			window.braintree = window.braintree || {};
			window.braintree.client = chBraintreeClient;
			window.braintree.hostedFields = chBraintreeHostedFields;

			this.fastlane = await paypal.fastlane();

			return this.fastlane;
		},

		renderFastlaneWatermark: async function() {
			try {
				await this.fastlane.renderWatermark("fiserv-paypal-watermark-container");
			} catch (error) {
				console.error("Watermark rendering failed:", error);
			}
		},

		authenticateEmailForFastlane: async function () {

			const emailInputValue = $("#email-input").val();

			try {
				const authenticateResult = await this.fastlane.authenticate({
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
		}
	};
});
