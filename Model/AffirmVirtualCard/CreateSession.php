<?php
namespace Fiserv\Payments\Model\AffirmVirtualCard;

use Fiserv\Payments\Gateway\Config\AffirmVirtualCard\Config;
use Fiserv\Payments\Gateway\Config\CommerceHub\Config as CommerceHubConfig;
use Fiserv\Payments\Helper\MerchantPartnerHelper;
use Fiserv\Payments\Model\Adapter\CommerceHub\ChHttpAdapter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

/** Modern session creation: credentials stay on the server and sdkData is not persisted. */
class CreateSession
{
	private Config $config;
	private CommerceHubConfig $chConfig;
	private ChHttpAdapter $http;
	private SessionRegistry $registry;

	public function __construct(Config $config, CommerceHubConfig $chConfig, ChHttpAdapter $http, SessionRegistry $registry)
	{
		$this->config = $config;
		$this->chConfig = $chConfig;
		$this->http = $http;
		$this->registry = $registry;
	}

	public function execute(Quote $quote): array
	{
		$storeId = (int) $quote->getStoreId();
		$action = $this->config->getPaymentAction($storeId);
		// Only AUTHORIZE is explicitly documented for this integration. Do not infer sale support.
		if (!$this->config->isActive($storeId) || !$quote->getIsActive() || !$quote->getItemsCount()
			|| !in_array($action, [MethodInterface::ACTION_AUTHORIZE, 'AUTH'], true)) {
			throw new LocalizedException(__('Affirm Virtual Card is unavailable for this checkout.'));
		}
		$amount = round((float) $quote->getBaseGrandTotal(), 2);
		$currency = (string) $quote->getBaseCurrencyCode();
		if ($amount <= 0 || $currency !== 'USD') {
			throw new LocalizedException(__('Affirm Virtual Card is unavailable for this checkout.'));
		}
		$this->registry->assertCanCreate((int) $quote->getId());
		$this->registry->invalidate((int) $quote->getId());
		$payload = [
			'amount' => ['total' => $amount, 'currency' => $currency],
			'merchantDetails' => [
				'merchantId' => $this->chConfig->getMerchantId($storeId),
				'terminalId' => $this->chConfig->getTerminalId($storeId),
				'merchantPartner' => MerchantPartnerHelper::createMerchantPartner($this->chConfig),
			],
			'session' => ['intent' => 'AUTHORIZE', 'intentInitiator' => 'MERCHANT'],
		];
		// No shipping, return URLs, descriptors, boarding overrides or card credentials.
		$response = $this->http->sendRequest($payload, 'v1/sessions/create');
		$data = json_decode($response->getBody(), true);
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300
			|| !is_array($data) || !isset($data['sdkData'], $data['sessionId'])
			|| !is_string($data['sdkData']) || $data['sdkData'] === ''
			|| !is_string($data['sessionId']) || $data['sessionId'] === '') {
			throw new LocalizedException(__('Unable to initialize Affirm Virtual Card.'));
		}
		$this->registry->register($data['sessionId'], (int) $quote->getId(), $storeId, $amount, $currency);
		return ['sdkData' => $data['sdkData'], 'sessionId' => $data['sessionId']];
	}
}
