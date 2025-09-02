<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Subject\PayPal;

use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Helper;
use Fiserv\Payments\Gateway\Subject\SubjectReader as FiservSubjectReader;
use Fiserv\Payments\Logger\MultiLevelLogger;

/**
 * Class SubjectReader
 */
class SubjectReader extends FiservSubjectReader
{
	/**
	 * @var MultiLevelLogger
	 */
	private $logger; 
	
	public function __construct(Session $checkoutSession, MultiLevelLogger $logger)
	{
		parent::__construct($checkoutSession);
		$this->logger = $logger;
	}
	
	/**
	 * Reads PayPal response from the subject.
	 * Used in Handlers
	 *
	 * @param array $subject
	 * @return array
	 * @throws \InvalidArgumentException if the subject doesn't contain response.
	 */
	public function readPayPalResponse(array $subject)
	{
		if (!isset($subject['response'])) {
			throw new \InvalidArgumentException('PayPal response object does not exist.');
		}

		return $subject['response'];
	}

	/**
	 * Reads response object from subject
	 * Used in Validators
	 *
	 * @param array $subject
	 * @return array
	 */
	public function readPayPalResponseFromResponse(array $subject)
	{
		$response = Helper\SubjectReader::readResponse($subject);

		return $this->readPayPalResponse($response);
	}

	/**
	 * Recursively checks if a nested key exists in an array and returns its value or a default value.
	 *
	 * @param array $haystack The array to search.
	 * @param string $needle The key whose value we want to return. 
	 * @param array $path The path array containing keys to traverse the nested array.
	 * @return mixed The value found at the nested key or null.
	 */
	public static function getValueSafely(array $haystack, string $needle, array $path)
	{
		foreach ($path as $key) {
			if (isset($haystack[$key])) {
				$haystack = $haystack[$key];
			} else {
				return null;
			}
		}
		return isset($haystack[$needle]) ? $haystack[$needle] : null;
	}
}
