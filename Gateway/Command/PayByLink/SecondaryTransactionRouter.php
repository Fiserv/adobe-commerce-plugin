<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Command\PayByLink;

use Fiserv\Payments\Gateway\Subject\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;

/**
 * Routes a Pay by Link secondary transaction (capture/settle, refund, void,
 * cancel) to the command pool of the payment method the shopper actually used
 * to pay the link.
 *
 * The primary transaction (the charge) is performed externally when the shopper
 * completes the hosted payment link. The method used is reported on the return
 * URL and persisted on the order payment as `pbl_paid_method`
 * (see {@see \Fiserv\Payments\Model\PayByLink\PaymentLinkHandler::updateOrderStatus}).
 * This router reads that value, resolves the matching underlying command pool
 * (e.g. CommerceHub card pool, Orders API pool for PayPal/Venmo) and forwards
 * the same command code to it.
 */
class SecondaryTransactionRouter implements CommandInterface
{
    /**
     * @var string
     */
    private $commandCode;

    /**
     * @var CommandPoolInterface[] Routing key => command pool.
     */
    private $pools;

    /**
     * @var array<string, string> Normalized method token => routing key.
     */
    private $methodMap;

    /**
     * @var string
     */
    private $defaultPoolKey;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @var MultiLevelLogger
     */
    private $logger;

    /**
     * @param string $commandCode Command to run on the resolved pool (capture, settle, refund, void, cancel).
     * @param CommandPoolInterface[] $pools Routing key => command pool.
     * @param array<string, string> $methodMap Normalized method token => routing key.
     * @param string $defaultPoolKey Routing key used when the paid method is unknown/unmapped.
     * @param SubjectReader $subjectReader
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        string $commandCode,
        array $pools,
        array $methodMap,
        string $defaultPoolKey,
        SubjectReader $subjectReader,
        MultiLevelLogger $logger
    ) {
        $this->commandCode = $commandCode;
        $this->pools = $pools;
        $this->methodMap = $methodMap;
        $this->defaultPoolKey = $defaultPoolKey;
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @return void
     */
    public function execute(array $commandSubject)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $this->subjectReader->readPayment($commandSubject);
        $payment = $paymentDO->getPayment();

        $paidMethod = (string) $payment->getAdditionalInformation('pbl_paid_method');
        $poolKey = $this->resolvePoolKey($paidMethod);

        if (!isset($this->pools[$poolKey])) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('No Pay by Link command pool is configured for "%1".', $poolKey)
            );
        }

        $this->logger->logInfo(
            1,
            sprintf(
                'PBL %s routed to "%s" pool (paid method: "%s")',
                $this->commandCode,
                $poolKey,
                $paidMethod !== '' ? $paidMethod : 'unknown'
            )
        );

        $this->pools[$poolKey]->get($this->commandCode)->execute($commandSubject);
    }

    /**
     * Resolve the routing key from the persisted paid method.
     *
     * @param string $paidMethod
     * @return string
     */
    private function resolvePoolKey(string $paidMethod): string
    {
        $token = $this->normalize($paidMethod);

        if ($token !== '' && isset($this->methodMap[$token])) {
            return $this->methodMap[$token];
        }

        return $this->defaultPoolKey;
    }

    /**
     * Normalize a method label to a comparable token: uppercase alphanumerics only.
     *
     * e.g. "Apple Pay" => "APPLEPAY", "Payment Card" => "PAYMENTCARD".
     *
     * @param string $value
     * @return string
     */
    private function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }
}
