define([
    'ko',
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Fiserv_Payments/js/model/surcharge',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function(ko, AbstractTotal, quote, surchargeModel, priceUtils, $t) {

    return AbstractTotal.extend({
        defaults: {
            template: 'Fiserv_Payments/checkout/summary/surcharge',
            title: $t('Surcharge')
        },

        initObservable: function() {
            this._super();

            this.hasSurcharge = ko.computed(function() {
                const data = surchargeModel.surchargeData();
                return data !== null && data.applied === true;
            }, this);

            this.surchargeAmount = ko.computed(function() {
                if (!this.hasSurcharge()) return 0;
                return surchargeModel.surchargeData().amount;
            }, this);

            this.formattedAmount = ko.computed(function() {
                if (!this.hasSurcharge()) return '';
                return this.getFormattedPrice(surchargeModel.surchargeData().amount);
            }, this);

            this.disclosureText = ko.computed(function() {
                if (!this.hasSurcharge()) return '';
                return surchargeModel.surchargeData().disclosureText;
            }, this);

            this.adjustedGrandTotal = ko.computed(function() {
                if (!this.hasSurcharge()) return '';
                return this.getFormattedPrice(surchargeModel.surchargeData().grandTotal);
            }, this);

            return this;
        },

        getFormattedPrice: function(price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        }
    });
});