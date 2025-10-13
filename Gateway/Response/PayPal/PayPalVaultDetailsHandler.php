<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Response\PayPal;

use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Fiserv\Payments\Model\PayPal\ProviderCustomerIdFactory;

/**
 * PayPal Vault Details Handler
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PayPalVaultDetailsHandler implements HandlerInterface
{
    /** @var SubjectReader */
    private $subjectReader;
    /** @var MultiLevelLogger */
    private $logger;
    /** @var ProviderCustomerIdFactory */
    private $providerCustomerIdFactory;

    /**
     * PayPalVaultDetailsHandler constructor.
     */
    public function __construct(
        SubjectReader $subjectReader,
        MultiLevelLogger $logger,
        ProviderCustomerIdFactory $providerCustomerIdFactory
    ) {
        $this->subjectReader = $subjectReader;
        $this->logger = $logger;
        $this->providerCustomerIdFactory = $providerCustomerIdFactory;
    }

    /**
     * Handle PayPal provider customer ID storage only (no tokenization).
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        $chResponse = $this->subjectReader->readChResponse($response)[\Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient::RESPONSE_KEY];
        $payment = $paymentDO->getPayment();

        // Only save providerCustomerId if present
        if (
            isset($chResponse["customer"]["providerCustomerId"]) &&
            $payment->getOrder()->getCustomerId()
        ) {
            $this->storeProviderCustomerId(
                $payment->getOrder()->getCustomerId(),
                $chResponse["customer"]["providerCustomerId"]
            );

            // Set providerCustomerId to session or payment object if needed for subsequent calls
            $payment->setAdditionalInformation('providerCustomerId', $chResponse["customer"]["providerCustomerId"]);
        }
    }

    // ...existing code...

    /**
     * Store providerCustomerId for the customer.
     */
    private function storeProviderCustomerId($customerId, $providerCustomerId)
    {
        try {
            $providerCustomerIdModel = $this->providerCustomerIdFactory->create()->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->getFirstItem();
            if (!$providerCustomerIdModel->getId()) {
                $providerCustomerIdModel = $this->providerCustomerIdFactory->create();
                $providerCustomerIdModel->setCustomerId($customerId);
            }
            $providerCustomerIdModel->setPaypalProviderCustomerId($providerCustomerId);
            $providerCustomerIdModel->save();
            $this->logger->logInfo(1, "Stored providerCustomerId '{$providerCustomerId}' for customer ID: {$customerId}");
        } catch (\Exception $e) {
            $this->logger->logError(1, "Error storing providerCustomerId: " . $e->getMessage());
        }
    }
}
