<?php

namespace Fiserv\Payments\Gateway\Http\PayPal;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\ConfigInterface;

class TransferFactory implements TransferFactoryInterface
{
	/**
	 * @var TransferBuilder
	 */
	private $transferBuilder;

	/**
	 * @param TransferBuilder $transferBuilder
	 */
	public function __construct(
		TransferBuilder $transferBuilder
	) {
		$this->transferBuilder = $transferBuilder;
	}

	/**
	 * Builds gateway transfer object
	 *
	 * @param array $request
	 * @return TransferInterface
	 */
	public function create(array $request)
	{
		$this->transferBuilder->setBody($request['body'] ?? $request);

		// Set PayPal endpoint if provided
		if (!empty($request['uri'])) {
			$this->transferBuilder->setUri($request['uri']);
		}

		// Set PayPal headers (e.g., Authorization)
		if (!empty($request['headers']) && is_array($request['headers'])) {
			$this->transferBuilder->setHeaders($request['headers']);
		}

		return $this->transferBuilder->build();
	}
}
