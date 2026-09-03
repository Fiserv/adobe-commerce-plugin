<?php
namespace Fiserv\Payments\Block\CommerceHub\Pbl;

use Fiserv\Payments\Gateway\Config\PayByLink\Config as PblConfig;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Form\Cc;
use Magento\Payment\Model\Config;
use Magento\Payment\Model\MethodInterface;

/**
 * Class Form
 */
class Form extends Cc 
{
	protected $sessionQuote;

	protected $pblConfig;

	protected $_template = 'Fiserv_Payments::order/form/pbl.phtml';

	/**
	 * @param Context $context
	 * @param Config $paymentConfig
	 * @param Quote $sessionQuote
	 * @param PblConfig $pblConfig
	 */
	public function __construct(
		Context $context,
		Config $paymentConfig,
		Quote $sessionQuote,
		PblConfig $pblConfig,
		array $data = []
	) {
		parent::__construct($context, $paymentConfig, $data);
		$this->sessionQuote = $sessionQuote;
		$this->pblConfig = $pblConfig;
	}

	public function areNotesEnabled(): bool {
		return $this->pblConfig->isOptionalNoteEnabled($this->sessionQuote->getStoreId());
	}

	public function getPaymentPageId(): string {
		return (string) $this->pblConfig->getPaymentPageId($this->sessionQuote->getStoreId());
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

	public function getChargeTypeOptions(): array
	{
		$default = $this->getChargeTypeDefault();
		$options = [];
		$options[] = '<option value="' . MethodInterface::ACTION_AUTHORIZE_CAPTURE . '"' . ($default === MethodInterface::ACTION_AUTHORIZE_CAPTURE ? ' selected="selected"' : '') . '>' . __('Sale (Auth & Capture)') . '</option>';
		$options[] = '<option value="' . MethodInterface::ACTION_AUTHORIZE . '"' . ($default === MethodInterface::ACTION_AUTHORIZE ? ' selected="selected"' : '') . '>' . __('Authorize') . '</option>';

		return $options;
	}

	private function getDayDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getDaysInExpiration($defaultExpiryMinutes);
	}	
	
	private function getHourDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getHoursInExpiration($defaultExpiryMinutes);
	}	
	
	private function getMinuteDefault(): string {
		$defaultExpiryMinutes = (int) $this->pblConfig->getExpiryTime();
		
		return $this->getMinutesInExpiration($defaultExpiryMinutes);
	}

	private function getChargeTypeDefault(): string
	{
		$action = (string) $this->pblConfig->getPaymentAction();
        if ($action !== MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            return MethodInterface::ACTION_AUTHORIZE;
        }

		return $action;
	}
}
