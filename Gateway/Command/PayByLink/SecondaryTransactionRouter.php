<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace Fiserv\Payments\Gateway\Command\PayByLink;

use Fiserv\Payments\Gateway\Subject\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Framework\Exception\LocalizedException;
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
     * @param SubjectReader $subjectReader
     * @param MultiLevelLogger $logger
     */
    public function __construct(
        string $commandCode,
        array $pools,
        array $methodMap,
        SubjectReader $subjectReader,
        MultiLevelLogger $logger
    ) {
        $this->commandCode = $commandCode;
        $this->pools = $pools;
        $this->methodMap = $methodMap;
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

        if ($payment->getMethod() !== 'fiserv_pay_by_link'
            || $payment->getAdditionalInformation('pbl_status') !== 'paid'
            || !$payment->getAdditionalInformation('pbl_payment_verified')
            || !$payment->getAdditionalInformation('pbl_transaction_id')
        ) {
            throw new LocalizedException(__('A completed Pay by Link payment is required.'));
        }

        $paidMethod = (string) $payment->getAdditionalInformation('pbl_paid_method');
        $poolKey = $this->resolvePoolKey($paidMethod);

        if (in_array($this->commandCode, ['capture', 'settle'], true)
            && $payment->getAdditionalInformation('pbl_transaction_state') === 'CAPTURED'
        ) {
            throw new LocalizedException(__('This Pay by Link payment has already been captured.'));
        }

        if (!isset($this->pools[$poolKey])) {
            throw new LocalizedException(
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

        $commandCode = $this->commandCode === 'capture' ? 'settle' : $this->commandCode;
        $this->pools[$poolKey]->get($commandCode)->execute($commandSubject);
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

        throw new LocalizedException(__('The Pay by Link payment method is missing or unsupported.'));
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
