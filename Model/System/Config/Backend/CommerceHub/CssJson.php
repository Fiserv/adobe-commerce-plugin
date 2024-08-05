<?php
/**
 * CssJson config field backend model
 */

// @codingStandardsIgnoreFile

namespace Fiserv\Payments\Model\System\Config\Backend\CommerceHub;

class CssJson extends \Magento\Framework\App\Config\Value
{

	/**
	 * Encrypt value before saving
	 *
	 * @return void
	 */
	public function beforeSave()
	{
		$this->_dataSaveAllowed = false;
		$value = $this->getValue();
		if ($value != null && !empty($value))
		{
			$this->validateCssJson($value);
		}

		$this->_dataSaveAllowed = true;
	}

	private function validateCssJson($value) {
		if (json_decode($value) == null) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. CommerceHub Form CSS must be valid json."));
		}
	}
}
