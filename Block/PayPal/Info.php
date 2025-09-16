<?php
namespace Fiserv\Payments\Block\PayPal;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;

/**
 * Class Info
 */
class Info extends ConfigurableInfo
{
    public function __construct(
        Context $context,
        ConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
    }

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

        $this->setDataToTransfer(
            $transport,
            "Transaction",
            $paymentInfo->getData('last_trans_id')
        );

        return $transport;
    }
}
