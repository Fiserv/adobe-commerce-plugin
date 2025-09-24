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
        let paypalCode = 'fiserv_paypal';

        if (config[paypalCode] && config[paypalCode].isActive) {
            rendererList.push(
                {
                    type: paypalCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-paypal',
                }
            );
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
