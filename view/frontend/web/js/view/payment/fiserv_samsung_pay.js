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

        let samsungPayCode = 'fiserv_samsung_pay';

        if (config[samsungPayCode] && config[samsungPayCode].isActive) {
            rendererList.push(
                {
                    type: samsungPayCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-samsungpay',
                }
            );
        }

        return Component.extend({});
    }
);

