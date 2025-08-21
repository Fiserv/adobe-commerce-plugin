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

        console.log('[ApplePayComponent] Initializing payment renderer configuration.');

        let config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};
        console.log('[ApplePayComponent] Checkout configuration:', config);

        let applepayCode = 'fiserv_applepay';

        if (config[applepayCode] && config[applepayCode].isActive) {
            console.log(`[ApplePayComponent] Apple Pay is active. Adding payment renderer for type: ${applepayCode}`);
            rendererList.push(
                {
                    type: applepayCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-applepay',
                }
            );
        } else {
            console.log(`[ApplePayComponent] Apple Pay is not active or configuration is missing for type: ${applepayCode}`);
        }

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
