define([
    'Fiserv_Payments/js/model/locale'
], function (localeModel) {
    'use strict';

    return {
        //Subscribes a payment method view model to locale changes.
        observe: function (component, onLocaleChange) {
            if (component.localeSubscription) {
                return component.localeSubscription;
            }

            component.localeSubscription = localeModel.current.subscribe(() => {
                if (component.getCode() === component.isChecked()) {
                    onLocaleChange();
                }
            });

            return component.localeSubscription;
        }
    };
});

