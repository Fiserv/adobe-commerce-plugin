define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list'
	],
	function (
		Component,
		rendererList
	) {
		'use strict';

		let config = window.checkoutConfig.payment;
		let paypal_fastlane = 'paypal_fastlane';

		if (config[ch].isActive) {
			rendererList.push(
				{
					type: paypal_fastlane,
					component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-form'
				}
			);
		}

		/* Add view logic here if needed */
		return Component.extend({});
	}
);

