<?php
namespace Fiserv\Payments\Gateway\Request\CashApp;

use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Magento\Payment\Gateway\Request\BuilderInterface;

/** Capture the existing authorization, not a fresh charge using the customer's saved token. */
class TokenCaptureBuilder implements BuilderInterface
{
	private $requestBuilder;

	public function __construct(BuilderInterface $requestBuilder)
	{
		$this->requestBuilder = $requestBuilder;
	}

	public function build(array $buildSubject)
	{
		$result = $this->requestBuilder->build($buildSubject);
		$details = $result[ChCompositeBase::REQUEST_KEY]->getTransactionDetails();
		$details->setCreateToken(false);
		$details->setAccountVerification(false);
		$result[ChCompositeBase::ENDPOINT_KEY] = TokenChargeBuilder::CAPTURE_ENDPOINT;
		return $result;
	}
}
