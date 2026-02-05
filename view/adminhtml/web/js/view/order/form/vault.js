/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/* browser:true */
/* global define */
define([
	'jquery',
	'uiComponent',
	'Magento_Ui/js/modal/alert',
], ($, Class, alert) => {
	'use strict';

	return Class.extend({
		defaults: {
			$selector: null,
			selector: 'edit_form',
			fieldset: '',
			active: false,
			imports: {
				onActiveChange: 'active',
			},
		},

		/**
		 * Set list of observable attributes
		 * @returns {exports.initObservable}
		 */
		initObservable() {
			const self = this;

			self.$selector = $(`#${self.selector}`);
			self.$container = $(`#${self.container}`);
			self.$selector.on(
				`setVaultNotActive.${self.getCode()}`,
				() => {
					self.$selector.off(`submitOrder.${self.getCode()}`);
				},
			);
			self._super();

			self.initEventHandlers();

			return self;
		},

		/**
		 * Get payment code
		 * @returns {String}
		 */
		getCode() {
			return this.code;
		},

		getContainer() {
			return $(`#${this.container}`);
		},

		/**
		 * Init event handlers
		 */
		initEventHandlers() {
			$(this.getContainer()).find('[name="payment[token_switcher]"]')
				.on('click', this.selectPaymentMethod.bind(this));
		},

		/**
		 * Select current payment token
		 */
		selectPaymentMethod() {
			this.disableEventListeners();
			this.enableEventListeners();
		},

		/**
		 * Enable form event listeners
		 */
		enableEventListeners() {
			this.$selector.on(`submitOrder.${this.getCode()}`, this.submitOrder.bind(this));
		},

		/**
		 * Disable form event listeners
		 */
		disableEventListeners() {
			this.$selector.off('submitOrder');
		},

		/**
		 * Pre submit for order
		 * @returns {Boolean}
		 */
		submitOrder() {
			this.$selector.validate().form();
			this.$selector.trigger('afterValidate.beforeSubmit');
			$('body').trigger('processStop');

			// validate parent form
			if (this.$selector.validate().errorList.length > 0) {
				return false;
			}
			this.getPaymentToken();
		},

		/**
		 * Place order
		 */
		placeOrder() {
			this.$selector.trigger('realOrder');
		},

		/**
		 * Send request to get payment method nonce
		 */
		getPaymentToken() {
			const self = this;

			$('body').trigger('processStart');

			$.getJSON(self.tokenUrl, {
				public_hash: self.publicHash,
				customer_id: self.getCustomerId(),
			}).done((response) => {
				self.setPaymentDetails(response.paymentToken);
				self.placeOrder();
			}).fail((response) => {
				const failed = JSON.parse(response.responseText);

				self.error(failed.message);
			})
				.always(() => {
					$('body').trigger('processStop');
				});
		},

		getCustomerId() {
			return globalThis.order.customerId;
		},

		/**
		 * Store payment details
		 * @param {String} token
		 */
		setPaymentDetails(token) {
			this.createPublicHashSelector();

			this.$selector.find('[name="payment[public_hash]"]').val(this.publicHash);
			this.getContainer().find(`#${this.getTokenSelectorName()}`).val(token);
		},

		/**
		 * Creates public hash selector
		 */
		createPublicHashSelector() {
			let $input;

			if (this.getContainer().find(`#${this.getTokenSelectorName()}`).length === 0) {
				$input = $('<input>').attr(
					{
						type: 'hidden',
						id: this.getTokenSelectorName(),
						name: 'payment[payment_token]',
					},
				);

				$input.appendTo(this.getContainer());
				$input.prop('disabled', false);
			}
		},

		/**
		 * Show alert message
		 * @param {String} message
		 */
		error(message) {
			alert({
				content: message,
			});
		},

		/**
		 * Get selector name for nonce input
		 * @returns {String}
		 */
		getTokenSelectorName() {
			return `${this.getCode()}_payment_token`;
		},
	});
});
