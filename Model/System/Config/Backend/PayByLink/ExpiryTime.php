<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */

namespace Fiserv\Payments\Model\System\Config\Backend\PayByLink;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Backend model for Pay By Link Expiry Time validation
 */
class ExpiryTime extends Value
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param Json $serializer
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        Json $serializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Validate and serialize expiry time before saving
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        
        // Log what we receive
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/expiry_time_debug.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('beforeSave - Raw value: ' . print_r($value, true));
        
        if (is_array($value)) {
            $hours = isset($value['hours']) ? (int)$value['hours'] : 0;
            $minutes = isset($value['minutes']) ? (int)$value['minutes'] : 0;
            
            $logger->info('beforeSave - Hours: ' . $hours . ', Minutes: ' . $minutes);
            
            // Calculate total minutes
            $totalMinutes = ($hours * 60) + $minutes;
            
            // Maximum 4 hours = 240 minutes
            if ($totalMinutes > 240) {
                throw new LocalizedException(
                    __('Expiry time cannot exceed 4 hours. Please adjust your selection.')
                );
            }
            
            // Minimum validation (at least 1 minute)
            if ($totalMinutes < 1) {
                throw new LocalizedException(
                    __('Expiry time must be at least 1 minute. Please select hours and/or minutes.')
                );
            }

            // Serialize the array before saving
            $serialized = $this->serializer->serialize($value);
            $logger->info('beforeSave - Serialized value: ' . $serialized);
            $this->setValue($serialized);
        }
        
        return parent::beforeSave();
    }

    /**
     * Unserialize value after loading
     *
     * @return void
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        
        $value = $this->getValue();
        if ($value && !is_array($value)) {
            try {
                $this->setValue($this->serializer->unserialize($value));
            } catch (\Exception $e) {
                $this->setValue([]);
            }
        }
    }
}
