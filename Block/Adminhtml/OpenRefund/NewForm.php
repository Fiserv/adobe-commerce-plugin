<?php

declare(strict_types=1);

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Gateway\Config\CommerceHub\Config;
use Magento\Backend\Block\Template;

class NewForm extends Template
{
    /** @var Config */
    private $config;

    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/create');
    }

    public function isEnabled(): bool
    {
        return $this->config->isOpenRefundEnabled();
    }

    public function getDefaultCaptureFlag(): int
    {
        return (int)$this->config->getOpenRefundCaptureFlag();
    }
}

