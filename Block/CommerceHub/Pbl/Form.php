<?php
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

	protected $_template = 'Fiserv_Payments::order/form/pbl.phtml';

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

	public function areNotesEnabled(): bool {
		return $this->pblConfig->isOptionalNoteEnabled();	
	}

	public function getSelectOptions(int $start, int $end, int $default, bool $pad = false): array {
		$options = array();	
		for ($i = $start; $i <= $end; $i++) {
			$selected = ($i == $default) ? 'selected="true"' : '';
	        $text = $pad ? str_pad((string)$i, 2, '0', STR_PAD_LEFT) : (string)$i;
	        array_push($options, "<option value=\"{$i}\" { $selected }>{$text}</option>");
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
		$expirationMinutes = (int) $this->pblConfig->getMaxExpiry();

		$days = $this->getDaysInExpiration($expirationMinutes);
		$default = $this->getDayDefault();
		return $this->getSelectOptions(0, $days, $default);  
	}

	public function getHourOptions(): array 
	{
		$expirationMinutes = (int) $this->pblConfig->getMaxExpiry();

		$hours = $this->getHoursInExpiration($expirationMinutes);
		$default = $this->getHourDefault();
		return $this->getSelectOptions(0, $hours, $default);  
	}

	public function getMinuteOptions(): array {
		$expirationMinutes = (int) $this->pblConfig->getMaxExpiry();

		$minutes = $this->getMinutesInExpiration($expirationMinutes);
		$default = $this->getMinuteDefault();
		return $this->getSelectOptions(0, $minutes, $default);	
	}

	private function getDayDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getDaysInExpiration($defaultExpryMinutes);
	}	
	
	private function getHourDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getHoursInExpiration($defaultExpryMinutes);
	}	
	
	private function getMinuteDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getMinutesInExpiration($defaultExpryMinutes);
	}
}
