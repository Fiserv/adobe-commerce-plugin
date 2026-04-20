<?php

namespace Fiserv\Payments\Model\Source\OpenRefund;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pending')],
            ['value' => 'success', 'label' => __('Success')],
            ['value' => 'failed',  'label' => __('Failed')],
        ];
    }
}

