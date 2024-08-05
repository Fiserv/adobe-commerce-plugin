<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\Customer\CommerceHub;

use Fiserv\Payments\Model\Config\CommerceHub\ConfigProvider;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;
use Magento\Vault\Model\CreditCardTokenFactory;

/**
 * Class VaultCardRenderer
 *
 * @api
 * @since 100.1.3
 */
class VaultCardRenderer extends AbstractCardRenderer
{

	/**
	 * @return string
	 * @since 100.1.0
	 */
	public function getIconUrl()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['url'];
	}

	/**
	 * @return int
	 * @since 100.1.0
	 */
	public function getIconHeight()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['height'];
	}

	/**
	 * @return int
	 * @since 100.1.0
	 */
	public function getIconWidth()
	{
		return $this->getIconForType($this->getTokenDetails()['type'])['width'];
	}

	/**
	 * Can render specified token
	 *
	 * @param PaymentTokenInterface $token
	 * @return boolean
	 * @since 100.1.3
	 */
	public function canRender(PaymentTokenInterface $token)
	{
		return $token->getPaymentMethodCode() === ConfigProvider::CODE;
	}

	/**
	 * @return string
	 * @since 100.1.0
	 */
	public function getNumberLast4Digits()
	{
		return $this->getTokenDetails()['maskedCC'];
	}

	/**
	 * @return string
	 * @since 100.1.0
	 */
	public function getExpDate()
	{
		return $this->getTokenDetails()['expirationDate'];
	}

    public function getType()
    {
        return CreditCardTokenFactory::TOKEN_TYPE_CREDIT_CARD;
    }
}
