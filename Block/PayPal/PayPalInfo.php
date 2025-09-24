<?php
namespace Fiserv\Payments\Block\PayPal;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class Info
 */
class PayPalInfo extends ConfigurableInfo
{
    public function __construct(
        Context $context,
        ConfigInterface $config,
        MultiLevelLogger $logger,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->logger = $logger;
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
