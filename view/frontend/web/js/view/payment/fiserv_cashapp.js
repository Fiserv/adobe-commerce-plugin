define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list'
	],
	function (Component, rendererList) {
		'use strict';

		let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
		let cashappCode = 'fiserv_cashapp';

		if (config[cashappCode] && config[cashappCode].isActive) {
			rendererList.push({
				type: cashappCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-cashapp'
			});
		}

		return Component.extend({});
	}
);
