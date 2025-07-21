<?php

namespace Fiserv\Payments\Block\CommerceHub\AdminHtml\Declines;

class Transaction extends \Magento\Backend\Block\Template
{
	const KEY_TRANSACTION = 'transaction';
	
	public function __construct(
		\Magento\Backend\Block\Template\Context $context,
		array $data = []
	) { 
		parent::__construct($context, $data);
	}

	public function getTransactionDetails()
	{
		$transaction = $this->getData(self::KEY_TRANSACTION);
		if (!isset($transaction) || empty($transaction) || !isset($transaction[0]))
		{
			throw new \Exception("Unable to locate transaction.");
		}

		return $transaction[0];
	}
}
