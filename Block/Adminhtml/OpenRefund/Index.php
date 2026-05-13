<?php

declare(strict_types=1);

namespace Fiserv\Payments\Block\Adminhtml\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Backend\Block\Template;

class Index extends Template
{
    /** @var CollectionFactory */
    private $collectionFactory;

    public function __construct(
        Template\Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionFactory = $collectionFactory;
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('fiserv/openrefund/new');
    }

    public function getRows(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(50);

        return $collection->getItems();
    }
}

