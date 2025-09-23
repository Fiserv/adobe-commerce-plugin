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

        let config = window.checkoutConfig.payment;
        let ch = 'fiserv_commercehub';
        
        if (config[ch].isActive) {
            rendererList.push(
                {
                    type: ch,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-form'
                }
            );
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
