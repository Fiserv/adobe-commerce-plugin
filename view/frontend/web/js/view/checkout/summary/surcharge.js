define([
    'ko',
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Fiserv_Payments/js/model/surcharge',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function (ko, AbstractTotal, quote, surchargeModel, priceUtils, $t) {
    'use strict';

    return AbstractTotal.extend({
        defaults: {
            template: 'Fiserv_Payments/checkout/summary/surcharge',
            title: $t('Surcharge')
        },

        initObservable: function () {
            this._super();

            this.hasSurcharge = ko.computed(function () {
                var data = surchargeModel.surchargeData();
                return data !== null && data.applied === true && Number(data.amount) > 0;
            }, this);

            this.surchargeAmount = ko.computed(function () {
                var data = surchargeModel.surchargeData();
                return (data && data.amount) ? data.amount : 0;
            }, this);

            this.formattedAmount = ko.computed(function () {
                var amount = this.surchargeAmount();
                if (amount > 0) {
                    return this.getFormattedPrice(amount);
                }
                return '';
            }, this);

            this.disclosureText = ko.computed(function () {
                var data = surchargeModel.surchargeData();
                return (data && data.disclosureText) ? data.disclosureText : '';
            }, this);

            this.adjustedGrandTotal = ko.computed(function () {
                var data = surchargeModel.surchargeData();
                if (data && data.grandTotal) {
                    return this.getFormattedPrice(data.grandTotal);
                }
                return '';
            }, this);

            return this;
        },

        getFormattedPrice: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        }
    });
});
