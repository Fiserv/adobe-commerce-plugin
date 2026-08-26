<?php

namespace Fiserv\Payments\Model\PayByLink;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;

class Method extends AbstractMethod
{
	/** Payment method code must match etc/payment.xml and config.xml */
	const PAYMENT_METHOD_CODE = 'fiserv_pay_by_link';

	protected $_code = self::PAYMENT_METHOD_CODE;

	/**
	 * Admin form block used when the radio button is selected
	 * (render simple text)
	 */
	protected $_formBlockType = \Fiserv\Payments\Block\CommerceHub\Pbl\Form::class;

	/**
	 * Optional info block (for order view)
	 */
	protected $_infoBlockType = \Fiserv\Payments\Block\Info\PayByLink::class;

	/** Capabilities — align with config.xml flags */
	protected $_isGateway               = false;
	protected $_canAuthorize            = false;
	protected $_canCapture              = true;
	protected $_canCapturePartial       = true;
	protected $_canRefund               = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid                 = true;
	protected $_isInitializeNeeded      = true;

	/** Availability scopes */
	protected $_canUseCheckout          = false; // storefront off
	protected $_canUseInternal          = true;  // admin on
	protected $_canUseForMultishipping  = false;

	private $paymentDataObjectFactory;

	private $commandPool;

	public function __construct(
		Context $context,
		Registry $registry,
		ExtensionAttributesFactory $extensionFactory,
		AttributeValueFactory $customAttributeFactory,
		PaymentData $paymentData,
		ScopeConfigInterface $scopeConfig,
		Logger $logger,
		PaymentDataObjectFactory $paymentDataObjectFactory,
		CommandPoolInterface $commandPool,
		?AbstractResource $resource = null,
		?AbstractDb $resourceCollection = null,
		array $data = []
	) {
		parent::__construct(
			$context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$resource,
			$resourceCollection,
			$data
		);
		$this->paymentDataObjectFactory = $paymentDataObjectFactory;
		$this->commandPool = $commandPool;
	}

	/**
	 * Initialize the payment method — prevents Magento from calling authorize/capture.
	 * Keeps order in STATE_NEW / pending status since payment happens externally via the link.
	 *
	 * @param string $paymentAction
	 * @param DataObject $stateObject
	 * @return $this
	 */
	public function initialize($paymentAction, $stateObject)
	{
		$stateObject->setState(Order::STATE_NEW);
		$stateObject->setStatus('pending');
		$stateObject->setIsNotified(false);

		return $this;
	}

	/**
	 * Optional: Restrict availability to "active" config.
	 */
	public function isAvailable(?CartInterface $quote = null)
	{
		return (bool)$this->_scopeConfig->isSetFlag(
			'payment/' . self::PAYMENT_METHOD_CODE . '/active',
			\Magento\Store\Model\ScopeInterface::SCOPE_STORE
		);
	}

	/**
	 * Normalize admin form additional_data before quote/order persistence.
	 */
	public function assignData(DataObject $data)
	{
		$additionalData = $data->getData('additional_data');
		if (is_array($additionalData)) {
			foreach (['pbl_expr_days', 'pbl_expr_hours', 'pbl_expr_minutes'] as $key) {
				if (array_key_exists($key, $additionalData)) {
					$additionalData[$key] = $this->normalizeIntegerValue($additionalData[$key]);
				}
			}

			if (array_key_exists('pbl_use_default_expiry', $additionalData)) {
				$additionalData['pbl_use_default_expiry'] = $this->normalizeBooleanStringValue(
					$additionalData['pbl_use_default_expiry']
				);
			}

			foreach (['pbl_payment_action', 'pbl_notes'] as $key) {
				if (array_key_exists($key, $additionalData)) {
					$additionalData[$key] = $this->normalizeStringValue($additionalData[$key]);
				}
			}

			$data->setData('additional_data', $additionalData);
		}

		parent::assignData($data);

		$payment = $this->getInfoInstance();
		if (!$payment) {
			return $this;
		}

		if (!is_array($additionalData)) {
			return $this;
		}

		$integerKeys = [
			'pbl_expr_days',
			'pbl_expr_hours',
			'pbl_expr_minutes',
		];
		$stringKeys = [
			'pbl_payment_action',
			'pbl_notes',
		];

		foreach ($integerKeys as $key) {
			if (array_key_exists($key, $additionalData)) {
				$payment->setAdditionalInformation($key, $this->normalizeIntegerValue($additionalData[$key]));
			}
		}

		if (array_key_exists('pbl_use_default_expiry', $additionalData)) {
			$payment->setAdditionalInformation(
				'pbl_use_default_expiry',
				$this->normalizeBooleanStringValue($additionalData['pbl_use_default_expiry'])
			);
		}

		foreach ($stringKeys as $key) {
			if (array_key_exists($key, $additionalData)) {
				$payment->setAdditionalInformation($key, $this->normalizeStringValue($additionalData[$key]));
			}
		}

		return $this;
	}

	/**
	 * Capture an authorized Pay by Link transaction.
	 */
	public function capture(InfoInterface $payment, $amount)
	{
		$this->ensureReferenceTransactions($payment);
		$this->executeCommerceHubCommand('capture', $payment, $amount);

		return $this;
	}

	/**
	 * Refund a captured Pay by Link transaction.
	 */
	public function refund(InfoInterface $payment, $amount)
	{
		$this->ensureReferenceTransactions($payment);
		$this->executeCommerceHubCommand('refund', $payment, $amount);

		return $this;
	}

	/**
	 * Void an authorized Pay by Link transaction.
	 */
	public function void(InfoInterface $payment)
	{
		$this->ensureReferenceTransactions($payment);
		$this->executeCommerceHubCommand('void', $payment);

		return $this;
	}

	private function ensureReferenceTransactions(InfoInterface $payment): void
	{
		$pblTxnId = (string) $payment->getAdditionalInformation('pbl_transaction_id');
		if ($pblTxnId === '') {
			return;
		}

		if (!$payment->getLastTransId()) {
			$payment->setLastTransId($pblTxnId);
		}

		if (!$payment->getParentTransactionId()) {
			$payment->setParentTransactionId($pblTxnId);
		}

		$payment->setTransactionId($pblTxnId);
		$payment->setShouldCloseParentTransaction(false);
		$payment->setIsTransactionClosed(false);
	}

	private function executeCommerceHubCommand(string $commandCode, InfoInterface $payment, $amount = null): void
	{
		$commandSubject = [
			'payment' => $this->paymentDataObjectFactory->create($payment),
		];

		if ($amount !== null) {
			$commandSubject['amount'] = (float) $amount;
		}

		$this->commandPool->get($commandCode)->execute($commandSubject);
	}

	private function normalizeIntegerValue($value): int
	{
		$value = $this->extractScalar($value, 0);

		return (int) $value;
	}

	private function normalizeStringValue($value): string
	{
		$value = $this->extractScalar($value, '');

		return (string) $value;
	}

	private function normalizeBooleanStringValue($value): string
	{
		$value = $this->extractScalar($value, 0);

		return (string) (int) (bool) $value;
	}

	/**
	 * @param mixed $value
	 * @param mixed $default
	 * @return mixed
	 */
	private function extractScalar($value, $default)
	{
		while (is_array($value) && !empty($value)) {
			$value = reset($value);
		}

		return is_array($value) ? $default : $value;
	}
}
