<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Payments\Gateway\Command\PayPal;

use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Fiserv\Payments\Gateway\Subject\PayPal\SubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;
use Fiserv\Payments\Model\System\Utils\Paypal\PaypalTokenUtil;

/**
 * Class GetPaypalTokenCommand
 */
class GetPaypalTokenCommand implements CommandInterface
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
        $payPalToken = $this->tokenManagement->getByPublicHash($publicHash, $customerId);
        if (!$payPalToken) {
            $this->logger->logWarning(1, "No available PayPal payment tokens");
            throw new \Exception('No available PayPal payment tokens');
        }

        $this->logger->logInfo(1, "PayPal payment tokens retrieved");
        return $this->resultFactory->create(['array' => ['PayPalToken' => $payPalToken->getGatewayToken()]]);
    }
}
