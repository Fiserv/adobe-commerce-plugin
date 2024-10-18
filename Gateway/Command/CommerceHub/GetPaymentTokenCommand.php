<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Gateway\Command\CommerceHub;

use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class GetPaymentTokenCommand
 */
class GetPaymentTokenCommand implements CommandInterface
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * @var ArrayResultFactory
     */
    private $resultFactory;

    /**
     * @var SubjectReader
     */
    private $subjectReader;

    /**
     * @param PaymentTokenManagementInterface $tokenManagement
     * @param ArrayResultFactory $resultFactory
     * @param SubjectReader $subjectReader
	 * @param MultiLevelLogger $logger
     */
    public function __construct(
        PaymentTokenManagementInterface $tokenManagement,
        ArrayResultFactory $resultFactory,
        SubjectReader $subjectReader,
		MultiLevelLogger $logger
    ) {
        $this->tokenManagement = $tokenManagement;
        $this->resultFactory = $resultFactory;
        $this->subjectReader = $subjectReader;
		$this->logger = $logger;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute(array $commandSubject)
    {
		$this->logger->logInfo(1, "Retrieving payment tokens");
        $publicHash = $this->subjectReader->readPublicHash($commandSubject);
        $customerId = $this->subjectReader->readCustomerId($commandSubject);
        $paymentToken = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
        if (!$paymentToken) {
			$this->logger->logWarning(1, "No available payment tokens");
            throw new \Exception('No available payment tokens');
        }

		$this->logger->logInfo(1, "Payment tokens retrieved");
        return $this->resultFactory->create(['array' => ['paymentToken' => $paymentToken->getGatewayToken()]]);
    }
}
