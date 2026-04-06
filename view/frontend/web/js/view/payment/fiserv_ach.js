/* eslint-disable unicorn/filename-case, prefer-arrow-callback, unicorn/prefer-module, @stylistic/max-len */
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

		const config = (globalThis.checkoutConfig && globalThis.checkoutConfig.payment) ? globalThis.checkoutConfig.payment : {};
		const achCode = 'fiserv_ach';

		if (config[achCode] && config[achCode].isActive) {
			rendererList.push(
				{
					type: achCode,
					component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-ach-form',
				},
			);
		}

		return Component.extend({});
	},
);
