<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Request\PayByLink;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\MethodInterface;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Fiserv\Payments\Model\PayByLink\PaymentLinkHandler;
use Fiserv\Payments\Helper\PayByLink\ExpiryTimeConverter;

/**
 * Assembles the inputs for a Pay by Link /v1/sessions/create request from the
 * order payment placed through the gateway "order" command.
 */
class SessionDataBuilder implements BuilderInterface
{
    /**
     * @var PblConfig
     */
    private $pblConfig;

    /**
     * @var PaymentLinkHandler
     */
    private $paymentLinkHandler;

    /**
     * @param PblConfig $pblConfig
     * @param PaymentLinkHandler $paymentLinkHandler
     */
    public function __construct(
        PblConfig $pblConfig,
        PaymentLinkHandler $paymentLinkHandler
    ) {
        $this->pblConfig = $pblConfig;
        $this->paymentLinkHandler = $paymentLinkHandler;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $buildSubject['payment'] ?? null;
        if ($paymentDO === null) {
            return [];
        }

        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();

        $additionalInfo = $payment->getAdditionalInformation();
        $expiresInMinutes = $this->resolveExpiryMinutes($additionalInfo, $storeId);
        $paymentAction = $this->resolvePaymentAction($additionalInfo, $storeId);

        return [
            'total' => (float) $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode() ?: 'USD',
            'expiresInMinutes' => $expiresInMinutes,
            'pageId' => $this->pblConfig->getPaymentPageId($storeId),
            'orderData' => $this->paymentLinkHandler->buildOrderData($order),
            'orderId' => $order->getIncrementId(),
            'paymentAction' => $paymentAction,
            'storeId' => $storeId,
        ];
    }

    /**
     * Resolve the expiry minutes from form input or config default.
     *
     * @param array|null $additionalInfo
     * @param int|null $storeId
     * @return int
     */
    private function resolveExpiryMinutes($additionalInfo, $storeId)
    {
        // If the user checked "use default expiry", skip form fields entirely
        if (is_array($additionalInfo) && !empty($additionalInfo['pbl_use_default_expiry'])) {
            return $this->pblConfig->getExpiryTime($storeId);
        }

        // Check if expiry was set from the admin form (pbl-expr fields)
        if (is_array($additionalInfo)) {
            $total = ExpiryTimeConverter::partsToMinutes(
                $additionalInfo['pbl_expr_days'] ?? 0,
                $additionalInfo['pbl_expr_hours'] ?? 0,
                $additionalInfo['pbl_expr_minutes'] ?? 0
            );
            if ($total > 0) {
                return $total;
            }
        }

        // Fall back to config default
        return $this->pblConfig->getExpiryTime($storeId);
    }

    /**
     * Resolve charge type from form input or config default.
     *
     * @param array|null $additionalInfo
     * @param int|null $storeId
     * @return string
     */
    private function resolvePaymentAction($additionalInfo, $storeId)
    {
        if (is_array($additionalInfo)) {
            $action = (string) ($additionalInfo['pbl_payment_action'] ?? '');
            if ($action === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                return MethodInterface::ACTION_AUTHORIZE_CAPTURE;
            }
        }

        $configured = (string) $this->pblConfig->getPaymentAction($storeId);
        if ($configured === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            return MethodInterface::ACTION_AUTHORIZE_CAPTURE;
        }

        return MethodInterface::ACTION_AUTHORIZE;
    }
}
