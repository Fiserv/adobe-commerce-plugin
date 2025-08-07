/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
	[
		'underscore',
		'jquery',
		'Magento_Payment/js/view/payment/cc-form',
		'Fiserv_Payments/js/ch-adapter',
		'Fiserv_Payments/js/action/create-commercehub-session',
		'Fiserv_Payments/js/action/modify-requirejs',
		'Magento_Checkout/js/model/quote',
		'Magento_Checkout/js/checkout-data',
		'Magento_Ui/js/model/messageList',
		'Magento_Vault/js/view/payment/vault-enabler',
		'Magento_Checkout/js/model/full-screen-loader',
		'Magento_Checkout/js/model/payment/additional-validators',
		'ko',
		'mage/translate',
		'domReady!'
	],
	function (
		_,
		$,
		Component,
		chAdapter,
		chSession,
		modifyRequirejs,
		quote,
		checkoutData,
		globalMessageList,
		VaultEnabler,
		fullScreenLoader,
		additionalValidators,
		ko,
		$t
	) {
		'use strict';

		return Component.extend({
			defaults: {
				template: 'Fiserv_Payments/payment/commercehub/form',
				active: false,
				code: 'fiserv_paypal_fastlane',
				paymentPayload: {
					sessionId: null,
					type: null,
					threeDSecureId: undefined
				},
				additionalData: {},
				paymentMethodName: '[name="payment[method]"',
				isIframeValid: false,
				credentials: undefined
			}
		});

	}
);
