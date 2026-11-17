/* eslint-disable unicorn/filename-case, unicorn/prefer-module -- Magento AMD registration. */
define(
	[
		'uiComponent',
		'Magento_Checkout/js/model/payment/renderer-list',
	],
	(
		Component,
		rendererList,
	) => {
		'use strict';

		const config = globalThis.checkoutConfig?.payment?.fiserv_afterpay;

		if (config?.isActive) {
			rendererList.push({
				type: 'fiserv_afterpay',
				component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-afterpay',
			});
		}

		return Component.extend({});
	},
);
