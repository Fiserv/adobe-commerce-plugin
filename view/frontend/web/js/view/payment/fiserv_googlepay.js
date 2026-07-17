define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
    ],
    function (
        Component,
        rendererList
    ) {

        const config = (window.checkoutConfig && window.checkoutConfig.payment) ? window.checkoutConfig.payment : {};

        const googlepayCode = 'fiserv_googlepay';

        if (config[googlepayCode] && config[googlepayCode].isActive) {
            rendererList.push(
                {
                    type: googlepayCode,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-googlepay',
                }
            );
        }

        return Component.extend({});
    }
);

