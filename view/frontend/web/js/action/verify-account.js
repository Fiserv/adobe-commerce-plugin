/**
 * Copyright  Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define([
    'jquery',
    'mage/storage',
    'mage/translate',
    'Magento_Ui/js/model/messageList'
], function ($, storage, $t, globalMessageList) {

    /**
     * Send an account-verification AJAX request to the Magento backend.
     * makes a server-to-server call to Commerce Hub's
     * POST /payments-vas/v1/accounts/verification endpoint and returns a JSON
     * @param  {Object} sessionData  For session-based verification: { sessionId: "<id>" }
     * For token-based verification:   { paymentToken: "<token>", expirationMonth: "MM", expirationYear: "YYYY" }
     * @return {jQuery.Deferred} Resolves on success, rejects on failure.
     */
    return function (sessionData) {
        const deferred = $.Deferred();

        const payload = JSON.stringify({
            sessionId: sessionData?.sessionId,
            paymentToken: sessionData?.paymentToken,
            expirationMonth: sessionData?.expirationMonth,
            expirationYear: sessionData?.expirationYear
        });

        storage.post(
            'fiserv/commercehub/verifyaccount',
            payload,
            false,
            'application/json'
        ).done(function (response) {
            if (response && response.success) {
                deferred.resolve(response);
            } else {
                const msg = (response && response.message)
                    ? response.message
                    : $t('Account verification failed. Please try a different card or payment method.');

                globalMessageList.addErrorMessage({message: msg});
                deferred.reject(response);
            }
        }).fail(function (xhr) {
            let msg = $t('An error occurred during account verification. Please try again.');
            if (xhr && xhr.statusText) {
                msg += ' (' + xhr.statusText + ')';
            }
            globalMessageList.addErrorMessage({message: msg});
            deferred.reject(xhr);
        });

        return deferred.promise();
    };
});
