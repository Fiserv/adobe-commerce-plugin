<?php

namespace Fiserv\Payments\Model\System\Config\Backend\CommerceHub;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validate "Checkout Interaction Expiration (Minutes)" setting.
 */
class CheckoutInteractionsExpiresInMinutes extends Value
{
	/**
	 * @inheritdoc
	 * @throws LocalizedException
	 */
	public function beforeSave()
	{
		$value = $this->getValue();

		if ($value === null || $value === '') {

			return parent::beforeSave();
		}

		if (!is_numeric($value) || (string)(int)$value !== (string)$value) {
			throw new LocalizedException(
				__("Checkout Interaction Expiration (Minutes) must be an integer.")
			);
		}

		$minutes = (int)$value;

		if ($minutes < 30 || $minutes > 240) {
			throw new LocalizedException(
				__("Checkout Interaction Expiration (Minutes) must be between %1 and %2.", 30, 240)
			);
		}

		$this->setValue($minutes);

		return parent::beforeSave();
	}
}
