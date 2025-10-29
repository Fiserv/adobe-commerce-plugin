function waitForElement(selector, callback, timeout = 10000) {
	const start = Date.now();
	const interval = setInterval(() => {
		const el = document.querySelector(selector);
		if (el) {
			clearInterval(interval);
			callback(el);
		} else if (Date.now() - start > timeout) {
			clearInterval(interval);
			console.warn(`Element ${selector} not found within timeout.`);
		}
	}, 100); // check every 100ms
}

waitForElement('#customer-email-fieldset .note', function () {
	require([
		'jquery',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-session',
		'Fiserv_Payments/js/view/payment/paypal/fastlane',
		'Fiserv_Payments/js/action/modify-requirejs',
		'Magento_Checkout/js/model/full-screen-loader'
	], function (
		$,
		chAdapter,
		chSession,
		fastlaneHelper,
		modifyRequirejs,
		fullScreenLoader
	) {
		async function initiateFastlane() {
			function isFastlaneEnabled(paypalCode) {
				return window.checkoutConfig.payment[paypalCode].fastlane;
			}

			let paypalCode = 'fiserv_paypal';

			if (isFastlaneEnabled(paypalCode)) {
				$('#customer-email-fieldset .note').hide().after('<div id="fiserv-paypal-watermark-container"></div>');

				let maps = {
					'braintree/client.min': 'chBraintreeClient',
					'braintree/hosted-fields.min': 'ch-braintree-hosted-fields'
				};
				modifyRequirejs(maps);

				let credsResponse = undefined;
				try {
					credsResponse = await chSession();
				} catch (error) {
					console.log("An error occurred while starting Commercehub payment session: ".concat(error));
					return;
				}

				let credentials = credsResponse["ch_credentials"];

				await chAdapter.initSdk(
					window.checkoutConfig.payment['fiserv_commercehub'],
					credentials
				);

				let fastlane = await fastlaneHelper.fastlaneInit();

				if (typeof(fastlane) === "undefined")
				{
					return;
				}
				await fastlaneHelper.renderFastlaneWatermark();

				const customerEmailInput = $("#customer-email");

				customerEmailInput.on("change", async function () {
					const method = { method: 'fiserv_commercehub' };
					$('#fiserv_commercehub').trigger('click');

					fullScreenLoader.startLoader();

					setTimeout( async function() {
						await fastlaneHelper.authenticateEmailForFastlane();
						fastlaneHelper.processAuthResult();
						fullScreenLoader.stopLoader();
					}, 1000);
				});

				if (customerEmailInput.val()) {
					customerEmailInput.trigger("change");
				}
			}
		}

		initiateFastlane();

	});
});
