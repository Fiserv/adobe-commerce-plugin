define([
	'jquery',
	'uiComponent',
	'Fiserv_Payments/js/action/modify-requirejs',
	'Magento_Customer/js/model/customer',
	'Fiserv_Payments/js/view/payment/paypal/fastlane',
	'Magento_Checkout/js/model/full-screen-loader'
], function (
	$,
	Component,
	modifyRequirejs,
	customerModel,
	fastlaneHelper,
	fullScreenLoader
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

			let fastlane = await fastlaneHelper.fastlaneInit();

			await fastlaneHelper.renderFastlaneWatermark();
	
			const fastlaneEmailSubmitButton = $("#email-submit-button");

			fastlaneEmailSubmitButton.on("click", function() {

				const method = { method: 'fiserv_commercehub' };
				$('#fiserv_commercehub').trigger('click');

				fullScreenLoader.startLoader();

				setTimeout(() => {
					fullScreenLoader.stopLoader();
					fastlaneHelper.authenticateEmailForFastlane();
				}, 3000);
			});

			return this;
		},
		isCustomerLoggedIn: function() {
			return customerModel.isLoggedIn();
		}
	});
});
