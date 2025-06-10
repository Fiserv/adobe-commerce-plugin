<?php
namespace Fiserv\Payments\Gateway\Request\CommerceHub;

use Fiserv\Payments\Lib\CommerceHub\Model\Customer;
use Fiserv\Payments\Lib\CommerceHub\Model\Phone;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Fiserv\Payments\Logger\MultiLevelLogger;

class CustomerDataBuilder implements BuilderInterface
{
	const CUSTOMER_KEY = 'customer';

	private $subjectReader;
	private $logger;

	public function __construct(
		SubjectReader $subjectReader,
		MultiLevelLogger $logger
	) {
		$this->subjectReader = $subjectReader;
		$this->logger = $logger;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->subjectReader->readPayment($buildSubject);
		$orderDO = $paymentDO->getOrder();

		$customerId = $orderDO->getCustomerId();
		$billing = $orderDO->getBillingAddress();

		$firstName = $billing ? $billing->getFirstname() : null;
		$lastName = $billing ? $billing->getLastname() : null;
		$email = $billing ? $billing->getEmail() : null;
		$phoneNumber = $billing ? $billing->getTelephone() : null;

		$phone = $phoneNumber ? [new Phone(['phone_number' => $phoneNumber])] : [];

		$customerModel = new Customer([
			'merchant_customer_id' => $customerId,
			'first_name' => $firstName,
			'last_name' => $lastName,
			'email' => $email,
			'phone' => $phone
		]);

		$orderIncrementId = $orderDO->getOrderIncrementId();
		$this->logger->logDebug(3, "Customer Data Builder:\n" . $customerModel->__toString(), "Order ID: $orderIncrementId");

		return [ self::CUSTOMER_KEY => $customerModel ];
	}
}
