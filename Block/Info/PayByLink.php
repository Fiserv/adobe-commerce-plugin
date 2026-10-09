<?php
namespace Fiserv\Payments\Block\Info;

use Magento\Payment\Block\Info;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class PayByLink extends Info
{
    protected $_template = 'Fiserv_Payments::info/paybylink.phtml';

    public function getPayByLinkUrl()
    {
        if ($this->isOrderCanceled()) {
            return '';
        }
        return $this->getInfo()->getAdditionalInformation('pbl_url') ?: '';
    }

    public function getTransactionId()
    {
        return $this->getInfo()->getAdditionalInformation('pbl_transaction_id') ?: '';
    }

    public function getExpiresInMinutes()
    {
        return (int) ($this->getInfo()->getAdditionalInformation('pbl_expires_in_minutes') ?: 0);
    }

    public function getCreatedAt()
    {
        return $this->getInfo()->getAdditionalInformation('pbl_created_at') ?: '';
    }

    public function getPblStatus()
    {
        if ($this->isOrderCanceled()) {
            return 'canceled';
        }
        return $this->getInfo()->getAdditionalInformation('pbl_status') ?: 'unknown';
    }

    public function isOrderCanceled(): bool
    {
        $payment = $this->getInfo();
        return $payment instanceof Payment && $payment->getOrder()
            && $payment->getOrder()->getState() === Order::STATE_CANCELED;
    }

    public function getPblError()
    {
        return $this->getInfo()->getAdditionalInformation('pbl_error') ?: '';
    }

    public function getPblErrorGuidance()
    {
        return $this->getInfo()->getAdditionalInformation('pbl_error_guidance') ?: '';
    }

    public function getPblErrorCode()
    {
        return $this->getInfo()->getAdditionalInformation('pbl_error_code') ?: '';
    }

    public function getExpiresAt()
    {
        $createdAt = $this->getCreatedAt();
        $expiresInMinutes = $this->getExpiresInMinutes();
        if ($createdAt && $expiresInMinutes > 0) {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
                return date('Y-m-d H:i:s', $ts + ($expiresInMinutes * 60));
            }
        }
        return '';
    }

    public function isExpired()
    {
        $expiresAt = $this->getExpiresAt();
        if ($expiresAt) {
            return time() > strtotime($expiresAt);
        }
        return false;
    }
}
