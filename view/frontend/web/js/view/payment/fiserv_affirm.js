define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';


        let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};

        let affirmCode = 'fiserv_affirm';

        if (config[affirmCode] && config[affirmCode].isActive) {
            rendererList.push(
                {
                    type: affirmCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-affirm',
                }
            );
        }

        return Component.extend({});
    }
);

