<?php
namespace Fiserv\Payments\Block\Adminhtml\Paybylink;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\FormKey;

/**
 * Renders the Pay-By-Link management listing. Row data is supplied by Controller\Adminhtml\Paybylink\Index
 */
class Manage extends Template
{
    protected $_template = 'Fiserv_Payments::dashboard/manage_payment_links.phtml';

    protected $formKey;

    public function __construct(
        Template\Context $context,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formKey = $formKey;
    }

    /**
     * Get the current form key for POST actions.
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get filter parameters from request
     */
    public function getFilterParams()
    {
        return [
            'order_number' => trim((string)$this->getRequest()->getParam('order_number', '')),
            'amount_from' => trim((string)$this->getRequest()->getParam('amount_from', '')),
            'amount_to' => trim((string)$this->getRequest()->getParam('amount_to', '')),
            'created_from' => trim((string)$this->getRequest()->getParam('created_from', '')),
            'created_to' => trim((string)$this->getRequest()->getParam('created_to', '')),
            'expires_from' => trim((string)$this->getRequest()->getParam('expires_from', '')),
            'expires_to' => trim((string)$this->getRequest()->getParam('expires_to', '')),
            'status' => trim((string)$this->getRequest()->getParam('status', '')),
            'search' => trim((string)$this->getRequest()->getParam('search', '')),
        ];
    }

    /**
     * Rows for the current page, as built by the controller.
     */
    public function getRows()
    {
        return $this->getData('rows') ?: [];
    }

    /**
     * Total pages for the current filter set, as computed by the controller.
     */
    public function getTotalPages()
    {
        return (int) ($this->getData('total_pages') ?: 1);
    }

    /**
     * Endpoint the JS AJAX search/filter/pagination requests are sent to —
     * the very same action that rendered this page.
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('fiserv/paybylink/index');
    }
}
