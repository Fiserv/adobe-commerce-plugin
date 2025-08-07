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
	let paypal_fastlane = 'paypal_fastlane_method';
        
        if (config[ch].isActive) {
            rendererList.push(
                {
                    type: ch,
                    component: 'Fiserv_Payments/js/view/payment/method-renderer/commercehub-form'
                }
            );
        }
	if( config['ch_paypal'].isFastlaneActive ) {
	   rendererList.push(
		{
		   type: paypal_fastlane,
		   component: 'Fiserv_Payments/js/view/payment/method-renderer/paypal-fastlane'
	        }
	   );
	}

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
