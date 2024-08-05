<?php
/**
 * Encrypted config field backend model
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Fiserv\Payments\Model\System\Config\Backend\CommerceHub;

class Encrypted extends \Magento\Config\Model\Config\Backend\Encrypted
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
		// don't save value, if an obscured value was received. This indicates that data was not changed.
		if (!preg_match('/^\*+$/', $value) && !empty($value)) {
			
			$enc_source = $this->getPath();
			switch ($enc_source) {
				case "payment/fiserv_commercehub/api_key":
					$this->validateApiKey($value);
					break;
				case "payment/fiserv_commercehub/api_secret":
					$this->validateApiSecret($value);
					break;
				case "payment/fiserv_commercehub/merchant_id":
					$this->validateMerchantId($value);
					break;
				case "payment/fiserv_commercehub/terminal_id":
					$this->validateTerminalId($value);
					break;
			}

			$this->_dataSaveAllowed = true;
			$encrypted = $this->_encryptor->encrypt($value);
			$this->setValue($encrypted);
		} else {
			$this->_dataSaveAllowed = false;
		}
	}

	private function validateApiKey($value) {
		if (strlen($value) != 32) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. CommerceHub API Key must be 32 digits. Your settings have not been saved."));
		}
	}

	private function validateApiSecret($value) {
		if (strlen($value) != 43) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. CommerceHub API Secret must be 43 digits. Your settings have not been saved."));
		}
	}

	private function validateMerchantId($value) {
		if (strlen($value) != 15) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. CommerceHub Merchant ID must be 15 digits. Your settings have not been saved."));
		}
	}

	private function validateTerminalId($value) {
		if (strlen($value) != 8) {
			$this->_dataSaveAllowed = false;
			throw new \Magento\Framework\Exception\LocalizedException(__("Error. CommerceHub Terminal ID must be 8 digits. Your settings have not been saved."));
		}
	}
}
