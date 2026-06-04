<?php
namespace Fiserv\Payments\Model\Source\ApplePay;

use Magento\Framework\Data\OptionSourceInterface;

class TokenProvider implements OptionSourceInterface
{
    const FISERV_SECURE_VAULT = 'FISERV_SECURE_VAULT';
    const PROVIDER_TOKEN = 'PROVIDER_TOKEN';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FISERV_SECURE_VAULT, 'label' => __('Fiserv Secure Vault')],
            ['value' => self::PROVIDER_TOKEN,       'label' => __('Provider Token')],
        ];
    }
}

