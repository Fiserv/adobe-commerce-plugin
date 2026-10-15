define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	function (
		Component,
		rendererList
	) {
		'use strict';

		const config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
		const affirmvirtualcardCode = 'fiserv_affirm_virutal_card';
		const affirmCode = 'fiserv_affirm';

		if (
			config[affirmvirtualcardCode] && config[affirmvirtualcardCode].isActive &&
			config[affirmCode] && config[affirmCode].isActive
		) {
			rendererList.push({
				type: affirmvirtualcardCode,
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-affirmvirtualcard'
			});
		}

		return Component.extend({});
	}
);

