<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Magento\Framework\Data\OptionSourceInterface;

class CustomerTokenOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Select a customer first --')],
        ];
    }
}

