<?php

declare(strict_types=1);

namespace Fiserv\Payments\Controller\Adminhtml\OpenRefund;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\DB\Helper as DbHelper;

/**
 * AJAX endpoint — searches customers by name or email for the open-refund customer picker.
 * GET  fiserv/openrefund/customersearch?query=<term>
 */
class CustomerSearch extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Fiserv_Payments::open_refund_create';
    private const MAX_RESULTS   = 10;

    /** @var JsonFactory */
    private $jsonFactory;

    /** @var CustomerCollectionFactory */
    private $customerCollectionFactory;

    /** @var DbHelper */
    private $dbHelper;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
	CustomerCollectionFactory $customerCollectionFactory,
	DbHelper $dbHelper
    ) {
        parent::__construct($context);
        $this->jsonFactory               = $jsonFactory;
	$this->customerCollectionFactory = $customerCollectionFactory;
	$this->dbHelper                  = $dbHelper;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $query  = trim((string)$this->getRequest()->getParam('query', ''));

        if (strlen($query) < 2) {
            return $result->setData([]);
        }

        try {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToSelect(['firstname', 'lastname', 'email']);

	    $like = $this->dbHelper->addLikeEscape($query, ['position' => 'any']);

            $collection->addAttributeToFilter(
                [
                    ['attribute' => 'email',     'like' => $like],
                    ['attribute' => 'firstname',  'like' => $like],
                    ['attribute' => 'lastname',   'like' => $like],
                ]
            );

            $collection->setPageSize(self::MAX_RESULTS);
            $collection->load();

            $customers = [];
            foreach ($collection as $customer) {
                $customers[] = [
                    'id'    => (int)$customer->getId(),
                    'name'  => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                    'email' => (string)$customer->getEmail(),
                ];
            }

            return $result->setData($customers);
        } catch (\Exception $e) {
            return $result->setData(['error' => __('Customer search failed.')]);
        }
    }
}

