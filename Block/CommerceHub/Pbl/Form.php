<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Fiserv\Payments\Block\CommerceHub\Pbl;

use Fiserv\Payments\Block\CommerceHub\Form as ChForm;
use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form\Cc;
use Magento\Payment\Model\Config;

/**
 * Class Form
 */
class Form extends Cc 
{
	protected $pblConfig;

	/**
	 * @param Context $context
	 * @param Config $paymentConfig
	 * @param PblConfig $pblConfig
	 */
	public function __construct(
		Context $context,
		Config $paymentConfig,
		PblConfig $pblConfig,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
		$this->pblConfig = $pblConfig;
	}

	public function areNotesEnabled(): boolean {
		return $this->pblConfig->isOptionalNoteEnabled();	
	}

	public function getSelectOptions(int $start, int $end, bool $pad = false): array {
		$options = array();	
		for ($i = $start; $i <= $end; $i++) {
	        $text = $pad ? str_pad((string)$i, 2, '0', STR_PAD_LEFT) : (string)$i;
	        array_push($options, "<option value=\"{$i}\">{$text}</option>");
		}

		return $options;
	}

	public function getDaysInExpiration(int $expirationMinutes): int
   	{
		$normalized = max(0, $expirationMinutes);
		$days = intdiv($normalized, 1440);
		if ($days * 1440 === $normalized && $days > 0)
		{
			$days--;
		}

		return $days;
	}

	public function getHoursInExpiration(int $expirationMinutes): int
   	{
		$days = $this->getDaysInExpiration($expirationMinutes);
		$minutesLeft = max(0, $expirationMinutes - ($days * 1440));
				
		$hours = intdiv($minutesLeft, 60);
		if ($minutesLeft > 0 && ($minutesLeft % 60 === 0) && $hours > 0)
		{
			$hours--;
		}

		return min($hours, 23);
	}

	public function getMinutesInExpiration(int $expirationMinutes): int
   	{
		$days = $this->getDaysInExpiration($expirationMinutes);
		$hours = $this->getHoursInExpiration($expirationMinutes);

		$minutesLeft = max(0, $expirationMinutes - (($days * 1440) + ($hours * 60)));
		return min($minutesLeft, 59);
	}

	public function getDayOptions(): array 
	{
		$expirationMinutes = (int) $this->pblConfig->getExpiryTime();

		$days = $this->getDaysInExpiration($expirationMinutes);
		return $this->getSelectOptions(0, $days);  
	}

	public function getHourOptions(): array 
	{
		$expirationMinutes = (int) $this->pblConfig->getExpiryTime();

		$hours = $this->getHoursInExpiration($expirationMinutes);
		return $this->getSelectOptions(0, $hours);  
	}

	public function getMinuteOptions(): array {
		$expirationMinutes = (int) $this->pblConfig->getExpiryTime();

		$minutes = $this->getMinutesInExpiration($expirationMinutes);
		return $this->getSelectOptions(0, $minutes);	
	}
}
