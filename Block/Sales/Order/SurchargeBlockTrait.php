<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Block\Sales\Order;

use Fiserv\Payments\Gateway\Response\CommerceHub\SurchargeResponseHandler;

/**
 * Shared logic for the storefront and admin surcharge totals blocks.
 * Both blocks extend different Magento base classes, so shared behaviour
 * lives here rather than in a common abstract.
 */
trait SurchargeBlockTrait
{
    /** Returns the surcharge amount or null when none was applied. */
    public function getSurchargeAmount(): ?float
    {
        $amount = $this->getOrder()
            ->getPayment()
            ->getAdditionalInformation(SurchargeResponseHandler::SURCHARGE_AMOUNT_KEY);
        if ((float)$amount <= 0) {
            return null;
        }
        return (float)$amount;
    }

    /** Register this block as a totals row inserted before Grand Total. */
    public function initTotals()
    {
        if ($this->getSurchargeAmount() === null) {
            return $this;
        }

        $total = new \Magento\Framework\DataObject([
            'code' => $this->getNameInLayout(),
            'block_name' => $this->getNameInLayout(),
        ]);

        $this->getParentBlock()->addTotalBefore($total, 'grand_total');

        return $this;
    }
}

