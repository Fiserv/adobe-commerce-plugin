define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';


        let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};

        let applepayCode = 'fiserv_applepay';

        if (config[applepayCode] && config[applepayCode].isActive) {
            rendererList.push(
                {
                    type: applepayCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-applepay'
                }
            );
        }

        return Component.extend({});
    }
);

