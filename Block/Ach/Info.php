<?php
namespace Fiserv\Payments\Block\Ach;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;

class Info extends ConfigurableInfo
{
    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        return __($field);
    }

    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);

        $paymentInfo = $this->getInfo();

        $accountNumber = $paymentInfo->getAdditionalInformation('accountNumber')
            ?? $paymentInfo->getAdditionalInformation('account_number')
            ?? $paymentInfo->getData('accountNumber');
        $transactionId = $paymentInfo->getData('last_trans_id')
            ?? $paymentInfo->getAdditionalInformation('transaction_id')
            ?? $paymentInfo->getAdditionalInformation('payment_session');

        if (!empty($accountNumber)) {
            $this->setDataToTransfer(
                $transport,
                'Account Number',
                $accountNumber
            );
        }

        if (!empty($transactionId)) {
            $this->setDataToTransfer(
                $transport,
                'Transaction ID',
                $transactionId
            );
        }

        return $transport;
    }
}
