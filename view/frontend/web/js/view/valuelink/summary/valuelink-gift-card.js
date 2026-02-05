/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
	'jquery',
	'ko',
	'Magento_Checkout/js/view/summary/abstract-total',
	'mage/url',
	'Magento_Checkout/js/model/totals',
	'Magento_Checkout/js/action/get-totals',
	'Fiserv_Payments/js/action/remove-valuelink-card-from-quote',
	'Magento_Checkout/js/action/get-payment-information',
	'Fiserv_Payments/js/action/remove-all-valuelink-card-from-quote',
	'Magento_Checkout/js/model/full-screen-loader',
], ($, ko, generic, url, totals, getTotalsAction, removeAction, infoAction, removeAllAction, loader) => {
	'use strict';

	return generic.extend({
		defaults: {
			template: 'Fiserv_Payments/valuelink/summary/valuelink-gift-card',
			appliedCards: [],
		},

		initPaymentListener() {
			// Need to detect failed payment requests and refresh totals
			// because Valuelink cards may have been removed from quote.
			$(document).on('ajaxError', (event_, xhr, settings) => { this.handleAjaxError(settings); });
		},

		handleAjaxError(settings) {
			// only handle if Valuelink cards have been applied
			if (this.appliedCards !== undefined && this.appliedCards.length > 0) {
				const url = new URL(settings.url);

				if (url.pathname.endsWith('/payment-information')) {
					removeAllAction();
				}
			}
		},

		refreshPaymentInfo() {
			removeAllAction();
		},

		/**
         * Get information about applied Valuelink gift cards and their amounts
         *
         * @returns {Array}.
         */
		getAppliedValuelinkCards() {
			if (totals.getSegment('fiserv_valuelink')) {
				this.appliedCards = JSON.parse(totals.getSegment('fiserv_valuelink').extension_attributes.valuelink_cards);

				return this.appliedCards;
			}

			return [];
		},

		/**
         * @return {Object|Boolean}
         */
		isAvailable() {
			return this.isFullMode() && totals.getSegment('fiserv_valuelink') &&
                totals.getSegment('fiserv_valuelink').value != 0; // eslint-disable-line eqeqeq
		},

		/**
         * @param {Number} usedBalance
         * @return {*|String}
         */
		getAmount(usedBalance) {
			return this.getFormattedPrice(usedBalance);
		},

		/**
         * @param {String} sessionId
         * @param {Object} event
         */
		removeValuelinkCard(sessionId, event) {
			event.preventDefault();

			if (sessionId) {
				removeAction(sessionId);
			}
		},
	});
});
