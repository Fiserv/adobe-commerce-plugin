/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
	'Magento_Ui/js/view/messages',
	'Fiserv_Payments/js/model/valuelink/valuelink-messages',
], (Component, messageContainer) => {
	'use strict';

	return Component.extend({
		/** @inheritdoc */
		initialize(config) {
			return this._super(config, messageContainer);
		},
	});
});
