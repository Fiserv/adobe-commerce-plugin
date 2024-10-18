/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Fiserv_Payments/js/view/valuelink/summary/valuelink-gift-card',
    'mage/url',
    'Magento_Checkout/js/model/totals'
], function ($, Component, url, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Fiserv_Payments/valuelink/cart/totals/valuelink-gift-card'
        },

        /**
         * @return {*|Boolean|Object|jQuery}
         */
        getRemoveUrl: function () {
            return url.build('/fiserv/valuelink/removevaluelinkcard');
        },

        /**
         * @override
         *
         * @returns {bool}
         */
        isAvailable: function () {
            return totals.getSegment('fiserv_valuelink') && totals.getSegment('fiserv_valuelink').value !== 0;
        },

        /**
         * @param {String} sessionId
         * @param {Object} event
         */
        removeValuelinkCard: function (sessionId, event) {
            event.preventDefault();

            if (sessionId) {
                $.post(this.getRemoveUrl(),
					{
						sessionId : sessionId
					})
				.always(function () {
                    location.reload();
                });
            }
        }
    });
});
