<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Config\PayByLink;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Model\MethodInterface;

/**
 * Forces the Magento payment action for Pay by Link to "order".
 *
 * This is independent of the merchant-facing charge type (Authorize vs Sale)
 * resolved by {@see Config::getPaymentAction()}: that value drives the gateway
 * link intent and is stored under the same config key, while this handler only
 * controls how Magento places the order. Using ACTION_ORDER routes order
 * placement through the gateway "order" command, which creates the payment link
 * session without authorizing/capturing at placement time.
 */
class PaymentActionHandler implements ValueHandlerInterface
{
    /**
     * @param array $subject
     * @param int|null $storeId
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(array $subject, $storeId = null)
    {
        return MethodInterface::ACTION_ORDER;
    }
}
