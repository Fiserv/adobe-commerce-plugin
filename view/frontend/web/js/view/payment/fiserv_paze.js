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
		let pazeCode = 'fiserv_paze';

		if (config[pazeCode] && config[pazeCode].isActive) {
			rendererList.push({
				type: pazeCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paze',
			});
		}

		/** Add view logic here if needed */
        	return Component.extend({});
	}
);
