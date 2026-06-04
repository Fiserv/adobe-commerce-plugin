<?php
namespace Fiserv\Payments\Model\Source\ApplePay;

use Magento\Framework\Data\OptionSourceInterface;

class SubscriptionType implements OptionSourceInterface
{
    const RECURRING   = 'RECURRING';
    const DEFERRED    = 'DEFERRED';
    const AUTO_RELOAD = 'AUTO_RELOAD';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::RECURRING,   'label' => __('Recurring')],
            ['value' => self::DEFERRED,    'label' => __('Deferred')],
            ['value' => self::AUTO_RELOAD, 'label' => __('Auto Reload')],
        ];
    }
}

