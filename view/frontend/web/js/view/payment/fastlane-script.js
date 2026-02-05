function waitForElement(selector, callback, timeout = 10_000) {
	const start = Date.now();
	const interval = setInterval(() => {
		const element = document.querySelector(selector);

		if (element) {
			clearInterval(interval);
			callback(element);
		} else if (Date.now() - start > timeout) {
			clearInterval(interval);
			console.warn(`Element ${selector} not found within timeout.`);
		}
	}, 100); // check every 100ms
}

waitForElement('#customer-email-fieldset .note', () => {
	require([
		'jquery',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-fastlane-session',
		'Fiserv_Payments/js/view/payment/paypal/fastlane',
		'Fiserv_Payments/js/action/modify-requirejs',
		'Magento_Checkout/js/model/full-screen-loader',
	], (
		$,
		chAdapter,
		chSession,
		fastlaneHelper,
		modifyRequirejs,
		fullScreenLoader,
	) => {
		async function initiateFastlane() {
			function isFastlaneEnabled(paypalCode) {
				return globalThis.checkoutConfig.payment[paypalCode].fastlane;
			}

			const paypalCode = 'fiserv_paypal';

			if (isFastlaneEnabled(paypalCode)) {
				$('#customer-email-fieldset .note').hide().after('<div id="fiserv-paypal-watermark-container"></div>');

				const maps = {
					'braintree/client.min': 'chBraintreeClient',
					'braintree/hosted-fields.min': 'ch-braintree-hosted-fields',
				};

				modifyRequirejs(maps);

				let credsResponse;

				try {
					credsResponse = await chSession();
				} catch (error) {
					console.log('An error occurred while starting Commercehub payment session: '.concat(error));

					return;
				}

				const credentials = credsResponse.ch_credentials;

				await chAdapter.initSdk(
					globalThis.checkoutConfig.payment.fiserv_commercehub,
					credentials,
				);

				const fastlane = await fastlaneHelper.fastlaneInit();

				if (fastlane === undefined) {
					return;
				}
				await fastlaneHelper.renderFastlaneWatermark();

				const customerEmailInput = $('#customer-email');

				customerEmailInput.on('change', async (event_) => {
					if (!event_.target.value) {
						fastlaneHelper.unengageFastlane();

						return;
					}

					fullScreenLoader.startLoader();

					await fastlaneHelper.authenticateEmailForFastlane();
					fastlaneHelper.processAuthResult();
					fullScreenLoader.stopLoader();
				});

				if (customerEmailInput.val()) {
					customerEmailInput.trigger('change');
				}
			}
		}

		initiateFastlane();
	});
});
