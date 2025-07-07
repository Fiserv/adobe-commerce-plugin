<?php
namespace Fiserv\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DisableValuelinkPaymentAction extends Field
{
	protected function _getElementHtml(AbstractElement $element)
	{
		$html = parent::_getElementHtml($element);

		$js = <<<HTML
			<script type="text/javascript">
				require([
					'Fiserv_Payments/js/disable-valuelink-payment-action'
				]);
			</script>
		HTML;
		return $html . $js;
	}
}
