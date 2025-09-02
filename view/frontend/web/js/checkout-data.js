define(['ko'], function(ko) {
    'use strict';

    var selectedPaymentMethod = ko.observable(null);

    return {
        getSelectedPaymentMethod: function() {
            return selectedPaymentMethod();
        },
        setSelectedPaymentMethod: function(method) {
            selectedPaymentMethod(method);
        }
    };
});
