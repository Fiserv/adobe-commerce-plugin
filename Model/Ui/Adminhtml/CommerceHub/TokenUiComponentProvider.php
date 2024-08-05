<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Model\Ui\Adminhtml\CommerceHub;

use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Fiserv\Payments\Model\PaymentTokenFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Ui\TokenUiComponentInterfaceFactory;
use Magento\Vault\Model\Ui\TokenUiComponentProviderInterface;

/**
 * Class TokenUiComponentProvider
 */
class TokenUiComponentProvider implements TokenUiComponentProviderInterface
{
	/**
	 * @var TokenUiComponentInterfaceFactory
	 */
	private $componentFactory;

	/**
	 * @var \Magento\Framework\UrlInterface
	 */
	private $urlBuilder;

	/**
	 * @param TokenUiComponentInterfaceFactory $componentFactory
	 * @param UrlInterface $urlBuilder
	 */
	public function __construct(
		TokenUiComponentInterfaceFactory $componentFactory,
		UrlInterface $urlBuilder
	) {
		$this->componentFactory = $componentFactory;
		$this->urlBuilder = $urlBuilder;
	}

	/**
	 * Get UI component for token
	 */
	public function getComponentForToken(PaymentTokenInterface $paymentToken)
	{		
		$jsonDetails = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
		$component = $this->componentFactory->create(
			[
				'config' => [
					'code' => ConfigProvider::VAULT_CODE,
					'tokenUrl' => $this->getTokenUrl(),
					TokenUiComponentProviderInterface::COMPONENT_DETAILS => $jsonDetails,
					TokenUiComponentProviderInterface::COMPONENT_PUBLIC_HASH => $paymentToken->getPublicHash(),
					'template' => 'Fiserv_Payments::order/form/vault.phtml'
				],
				'name' => Template::class
			]
		);

		return $component;
	}

	private function getTokenUrl()
	{
		return $this->urlBuilder->getUrl('fiserv/vault/getcommercehubtoken', ['_secure' => true]);
	}
}
