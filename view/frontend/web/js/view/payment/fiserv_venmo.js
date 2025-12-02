define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	function (
		Component,
		rendererList,
	) {
		'use strict';

		let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
		let venmoCode = 'fiserv_venmo';

		if (config[venmoCode] && config[venmoCode].isActive) {
			rendererList.push({
				type: venmoCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-venmo',
			});
		}

		/** Add view logic here if needed */
        	return Component.extend({});
	}
);
