<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Gateway\Subject\CommerceHub;

use Magento\Payment\Gateway\Helper;
use Fiserv\Payments\Gateway\Http\CommerceHub\Client\HttpClient;
use Fiserv\Payments\Gateway\Subject\SubjectReader as FiservSubjectReader;

/**
 * Class SubjectReader
 */
class SubjectReader extends FiservSubjectReader
{
	/**
	 * Reads response from the subject.
	 * Used in Handlers
	 *
	 * @param array $subject
	 * @return array
	 * @throws \InvalidArgumentException if the subject doesn't contain response.
	 */
	public function readChResponse(array $subject)
	{
		if (!isset($subject[HttpClient::RESPONSE_KEY])) {
			throw new \InvalidArgumentException('CommerceHub response object does not exist.');
		}

		if (!isset($subject[HttpClient::STATUS_CODE_KEY])) {
			throw new \InvalidArgumentException('CommerceHub response status code does not exist.');
		}

		return $subject;
	}

	/**
	 * Reads response object from subject
	 * Used in Validators
	 *
	 * @param array $subject
	 * @return CHRepsonse
	 */
	public function readChResponseFromResponse(array $subject)
	{
		$response = Helper\SubjectReader::readResponse($subject);

		return $this->readChResponse($response);
	}
}
