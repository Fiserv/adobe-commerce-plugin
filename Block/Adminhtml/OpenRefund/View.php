<?php

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\OpenRefund;
use Fiserv\Payments\Model\FailedTransactionRepository;
use Magento\Backend\Block\Template;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;

class View extends Template
{
	private $registry;

	private $customerRepository;

	private $failedTransactionRepository;

	public function __construct(
		Template\Context $context,
		Registry $registry,
		CustomerRepositoryInterface $customerRepository,
		FailedTransactionRepository $failedTransactionRepository,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->registry = $registry;
		$this->customerRepository = $customerRepository;
		$this->failedTransactionRepository = $failedTransactionRepository;
	}

	public function getBackUrl(): string
	{
		return $this->getUrl('fiserv/openrefund/index');
	}

	public function getOpenRefund(): ?OpenRefund
	{
		$openRefund = $this->registry->registry('fiserv_open_refund_current');
		return $openRefund instanceof OpenRefund ? $openRefund : null;
	}

	public function getDeclineUrl(OpenRefund $openRefund): string
	{
		$transactions = $this->failedTransactionRepository->getListByOrderIncrementId(
			$openRefund->getData(OpenRefund::KEY_OPEN_REFUND_ID)
		);
		foreach ($transactions as $transaction) {
			return $this->getUrl('fiserv/declines/transaction', ['transaction_id' => $transaction->getTransactionId()]);
		}

		return '';
	}

	public function getDeclinesDashboardUrl(): string
	{
		return $this->getUrl('fiserv/declines/preview');
	}

	public function getFieldLabels(): array
	{
		return [
			OpenRefund::KEY_ENTITY_ID => 'Entity ID',
			OpenRefund::KEY_OPEN_REFUND_ID => 'Open Refund ID',
			OpenRefund::KEY_STATUS => 'Status',
			OpenRefund::KEY_AMOUNT => 'Amount',
			OpenRefund::KEY_CURRENCY_CODE => 'Currency Code',
			OpenRefund::KEY_CUSTOMER_ID => 'Customer',
			OpenRefund::KEY_REFUND_REASON => 'Refund Reason',
			OpenRefund::KEY_ORDER_ID => 'Order ID',
			OpenRefund::KEY_CASE_NUMBER => 'Case Number',
			OpenRefund::KEY_LINKED_IDENTIFIER => 'Internal Reference',
			OpenRefund::KEY_CH_REFUND_ID => 'CommerceHub Refund ID',
			OpenRefund::KEY_ERROR_MESSAGE => 'Error Message',
			OpenRefund::KEY_NOTES => 'Notes',
			OpenRefund::KEY_ADMIN_USERNAME => 'Admin Username',
			OpenRefund::KEY_CREATED_AT => 'Created At',
			OpenRefund::KEY_UPDATED_AT => 'Updated At'
		];
	}

	public function formatValue(string $field, $value): string
	{
		if ($value === null || $value === '') {
			return '—';
		}

		if ($field === OpenRefund::KEY_AMOUNT) {
			return number_format((float)$value, 2);
		}

		if ($field === OpenRefund::KEY_CUSTOMER_ID) {
			return $this->getCustomerDisplay((int)$value);
		}

		if ($field === OpenRefund::KEY_STATUS) {
			return (string)__((string)$value);
		}

		if ($field === OpenRefund::KEY_REFUND_REASON) {
			$map = [
				'CUSTOMER_APPEASEMENT' => __('Customer Appeasement'),
				'TIME_SENSITIVE_REFUND' => __('Time-sensitive refund'),
				'FRAUD_CORRECTION' => __('Fraud correction'),
				'DUPLICATE_CHARGE' => __('Duplicate charge'),
				'OTHER' => __('Other')
			];
			$reason = strtoupper((string)$value);
			return isset($map[$reason]) ? (string)$map[$reason] : (string)$value;
		}

		return (string)$value;
	}

	private function getCustomerDisplay(int $customerId): string
	{
		if ($customerId <= 0) {
			return (string)__('Guest');
		}

		try {
			$customer = $this->customerRepository->getById($customerId);
			$name = trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname());
			$email = (string)$customer->getEmail();

			if ($name !== '' && $email !== '') {
				return $name . ' <' . $email . '>';
			}

			if ($email !== '') {
				return $email;
			}

			return (string)$customerId;
		} catch (NoSuchEntityException $e) {
			return (string)__('Customer not found (ID: %1)', $customerId);
		}
	}
}
