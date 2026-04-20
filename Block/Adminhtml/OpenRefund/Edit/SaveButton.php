<?php

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund\Edit;

use Magento\Backend\Block\Template;

/**
 * Renders the "Submit Refund" button for the Open Refund create form.
 *
 * The button is added to the page toolbar via layout XML (fiserv_openrefund_edit.xml)
 * rather than through the UI form XML, because the version of the Magento UI
 * component schema in use does not permit <settings> inside a <button> element.
 *
 * On click it delegates to the Magento UI form component's save() method via
 * uiRegistry, which in turn invokes our wrapped formComp.save() defined in
 * Fiserv_Payments/js/open-refund/form.js.
 */
class SaveButton extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Fiserv_Payments::open-refund/save-button.phtml';
}

