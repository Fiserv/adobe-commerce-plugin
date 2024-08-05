<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Subject;

use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper;
use Magento\Vault\Api\Data\PaymentTokenInterface;

/**
 * Class SubjectReader
 */
class SubjectReader
{
	/**
	 * @var Session
	 */
	private $checkoutSession;

	public function __construct(
		Session $checkoutSession
	) {
		$this->checkoutSession = $checkoutSession;
	}

	/**
	 * Reads payment from subject
	 *
	 * @param array $subject
	 * @return PaymentDataObjectInterface
	 */
	public function readPayment(array $subject)
	{
		return Helper\SubjectReader::readPayment($subject);
	}

	/**
	 * Reads amount from subject
	 *
	 * @param array $subject
	 * @return mixed
	 */
	public function readAmount(array $subject)
	{
		return Helper\SubjectReader::readAmount($subject);
	}

	/**
	 * Reads response from subject
	 *
	 * @param array $subject
	 * @return array
	 */
	public function readResponse(array $subject)
	{
		return Helper\SubjectReader::readResponse($subject);
	}

	/**
	 * @return \Magento\Sales\Model\Order
	 */
	public function getOrder()
	{
		return $this->checkoutSession->getLastRealOrder();
	}

	/**
	 * @return \Magento\Quote\Model\Quote
	 */
	public function getQuote()
	{
		return $this->checkoutSession->getQuote();
	}

	/**
	 * Reads customer id from subject
	 *
	 * @param array $subject
	 * @return int
	 */
	public function readCustomerId(array $subject)
	{
		if (!isset($subject['customer_id'])) {
			throw new \InvalidArgumentException('The "customerId" field does not exist');
		}

		return (int) $subject['customer_id'];
	}

	/**
	 * Reads public hash from subject
	 *
	 * @param array $subject
	 * @return string
	 */
	public function readPublicHash(array $subject)
	{
		if (empty($subject[PaymentTokenInterface::PUBLIC_HASH])) {
			throw new \InvalidArgumentException('The "public_hash" field does not exists');
		}

		return $subject[PaymentTokenInterface::PUBLIC_HASH];
	}
}
