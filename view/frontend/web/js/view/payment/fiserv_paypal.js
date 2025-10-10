define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-session',
		'Fiserv_Payments/js/view/payment/paypal/fastlane',
		'Fiserv_Payments/js/action/modify-requirejs'
	],
	function (
		Component,
		rendererList,
		chAdapter,
		chSession,
		fastlaneHelper,
		modifyRequirejs
	) {
		'use strict';

		function isFastlaneEnabled(paypalCode) {
			return window.checkoutConfig.payment[paypalCode].fastlane;
		}

		let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
		let paypalCode = 'fiserv_paypal';

		if (config[paypalCode] && config[paypalCode].isActive) {
			rendererList.push({
				type: paypalCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal',
			});
		}

		if( isFastlaneEnabled() ) {

			$('#customer-email-fieldset .note').hide().after('<div id="fiserv-paypal-watermark-container"></div>');
			let maps = {
				'braintree/client.min' : 'chBraintreeClient',
				'braintree/hosted-fields.min' : 'ch-braintree-hosted-fields'
			};
			modifyRequirejs( maps );

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

			let fastlane = await fastlaneHelper.fastlaneInit();

			await fastlaneHelper.renderFastlaneWatermark();

			const customerEmailInput = $("#customer-email");

			customerEmailInput.on("change", function() {

				const method = { method: 'fiserv_commercehub' };
				$('#fiserv_commercehub').trigger('click');

				fullScreenLoader.startLoader();

				setTimeout(() => {
					fullScreenLoader.stopLoader();
					fastlaneHelper.authenticateEmailForFastlane();
				}, 1000);
			});

			if(customerEmailInput.val()) {
				customerEmailInput.trigger("change");
			}

			const changeCard = $('.reset-ch-form');

			changeCard.on('click', function() {
				chAdapter.resetIframe();
				$('#fiserv-checkout-submit').attr('disabled', true);
			});
		}

		/** Add view logic here if needed */
        	return Component.extend({});
	}
);
