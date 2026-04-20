<?php

namespace Fiserv\Payments\Model\Ui\OpenRefund;

use Fiserv\Payments\Model\ResourceModel\OpenRefund\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    /** @var array */
    private array $loadedData = [];

    /** @var RequestInterface */
    private RequestInterface $request;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        RequestInterface $request,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->request    = $request;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData) {
            return $this->loadedData;
        }

        $entityId = (int) $this->request->getParam('entity_id');

        if ($entityId) {
            /** @var \Fiserv\Payments\Model\OpenRefund $item */
            foreach ($this->collection->getItems() as $item) {
                if ((int) $item->getEntityId() === $entityId) {
                    $this->loadedData[$entityId] = $item->getData();
                    break;
                }
            }
        }

        return $this->loadedData;
    }
}

