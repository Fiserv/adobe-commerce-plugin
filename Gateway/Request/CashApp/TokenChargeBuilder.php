<?php
namespace Fiserv\Payments\Gateway\Request\CashApp;

use Fiserv\Payments\Gateway\Request\CommerceHub\AmountDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\BillingAddressDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\CustomerDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\MerchantDetailsDataBuilder;
use Fiserv\Payments\Gateway\Request\CommerceHub\Composite\ChCompositeBase;
use Fiserv\Payments\Gateway\Subject\CommerceHub\SubjectReader;
use Fiserv\Payments\Lib\CommerceHub\Model\PaymentToken;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails;
use Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction;
use Fiserv\Payments\Model\CashApp\SavedPayment;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TokenChargeBuilder implements BuilderInterface
{
	public const AUTHORIZE_ENDPOINT = 'v1/payments/authorizations';
	public const CAPTURE_ENDPOINT = 'v1/payments/captures';

	private $reader;
	private $savedPayment;
	private $amount;
	private $merchant;
	private $billing;
	private $customer;
	private $capture;

	public function __construct(SubjectReader $reader, SavedPayment $savedPayment,
		AmountDataBuilder $amount, MerchantDetailsDataBuilder $merchant,
		BillingAddressDataBuilder $billing, CustomerDataBuilder $customer, bool $capture = false)
	{
		$this->reader = $reader;
		$this->savedPayment = $savedPayment;
		$this->amount = $amount;
		$this->merchant = $merchant;
		$this->billing = $billing;
		$this->customer = $customer;
		$this->capture = $capture;
	}

	public function build(array $buildSubject)
	{
		$paymentDO = $this->reader->readPayment($buildSubject);
		$token = $this->savedPayment->resolve($paymentDO->getPayment());
		$orderId = $paymentDO->getOrder()->getOrderIncrementId();
		$source = new PaymentToken();
		$source->setSourceType('PaymentToken');
		$source->setTokenData($token['tokenData']);
		$source->setTokenSource($token['tokenSource']);

		$details = new TransactionDetails();
		$details->setCaptureFlag($this->capture);
		$details->setMerchantTransactionId(uniqid());
		$details->setMerchantOrderId($orderId);
		$details->setCreateToken(false);
		$details->setAccountVerification(false);
		$interaction = new TransactionInteraction();
		$interaction->setOrigin('ECOM');
		$interaction->setPosConditionCode('CARD_NOT_PRESENT_ECOM');

		$request = [
			'amount' => $this->amount->build($buildSubject)[AmountDataBuilder::AMOUNT_KEY],
			'source' => $source,
			'transactionDetails' => $details,
			'merchantDetails' => $this->merchant->build($buildSubject)[MerchantDetailsDataBuilder::MERCHANT_DETAILS_KEY],
			'billingAddress' => $this->billing->build($buildSubject)[BillingAddressDataBuilder::BILLING_ADDRESS_KEY],
			'customer' => $this->customer->build($buildSubject)[CustomerDataBuilder::CUSTOMER_KEY],
			'transactionInteraction' => $interaction,
		];
		return [
			ChCompositeBase::REQUEST_KEY => $request,
			ChCompositeBase::ENDPOINT_KEY => $this->capture ? self::CAPTURE_ENDPOINT : self::AUTHORIZE_ENDPOINT,
			ChCompositeBase::ORDER_INCREMENT_ID_KEY => $orderId,
		];
	}
}
