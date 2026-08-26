<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */

namespace Fiserv\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Pay By Link Expiry Time Field (Hours and Minutes)
 */
class ExpiryTime extends Field
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Json $serializer
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Json $serializer,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $data);
    }

    /**
     * Get the element HTML with two dropdowns
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementName = $element->getName();
        $element->setStyle('width:70px;');
        
        // Days dropdown
        $daysHtml = '<select name="' . $elementName . '[days]" id="' . $element->getHtmlId() . '_days" class="select admin__control-select" style="width:100px;">';
        $daysValue = $this->getDaysValue($element);
        
        for ($i = 0; $i <= 1; $i++) {
            $selected = ($daysValue == $i) ? 'selected="selected"' : '';
            $label = $i . ' ' . ($i == 1 ? 'Day' : 'Days');
            $daysHtml .= '<option value="' . $i . '" ' . $selected . '>' . __($label) . '</option>';
        }
        $daysHtml .= '</select>';
        
        // Hours dropdown (0-23)
        $hoursHtml = '<select name="' . $elementName . '[hours]" id="' . $element->getHtmlId() . '_hours" class="select admin__control-select" style="width:100px; margin-left:10px;">';
        $hoursValue = $this->getHoursValue($element);
        
        for ($i = 0; $i <= 23; $i++) {
            $selected = ($hoursValue == $i) ? 'selected="selected"' : '';
            $label = $i . ' ' . ($i == 1 ? 'Hour' : 'Hours');
            $hoursHtml .= '<option value="' . $i . '" ' . $selected . '>' . __($label) . '</option>';
        }
        $hoursHtml .= '</select>';
        
        // Minutes dropdown
        $minutesHtml = '<select name="' . $elementName . '[minutes]" id="' . $element->getHtmlId() . '_minutes" class="select admin__control-select" style="width:110px; margin-left:10px;">';
        $minutesValue = $this->getMinutesValue($element);
        
        $minutesOptions = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 59];
        foreach ($minutesOptions as $minutes) {
            $selected = ($minutesValue == $minutes) ? 'selected="selected"' : '';
            $minutesHtml .= '<option value="' . $minutes . '" ' . $selected . '>' . __($minutes . ' Minutes') . '</option>';
        }
        $minutesHtml .= '</select>';
        
        return $daysHtml . $hoursHtml . $minutesHtml;
    }
    
    /**
     * Get hours value from config
     *
     * @param AbstractElement $element
     * @return int
     */
    protected function getHoursValue($element)
    {
        $value = $element->getValue();
        
        // If value is a string (serialized), unserialize it
        if (is_string($value) && !empty($value)) {
            try {
                $value = $this->serializer->unserialize($value);
            } catch (\Exception $e) {
                $value = [];
            }
        }
        
        if (is_array($value) && isset($value['hours'])) {
            return (int)$value['hours'];
        }
        return 0; // Default to 0 hours
    }
    
    /**
     * Get minutes value from config
     *
     * @param AbstractElement $element
     * @return int
     */
    protected function getMinutesValue($element)
    {
        $value = $element->getValue();
        
        // If value is a string (serialized), unserialize it
        if (is_string($value) && !empty($value)) {
            try {
                $value = $this->serializer->unserialize($value);
            } catch (\Exception $e) {
                $value = [];
            }
        }
        
        if (is_array($value) && isset($value['minutes'])) {
            return (int)$value['minutes'];
        }
        return 0; // Default to 0 minutes
    }
    
    /**
     * Get days value from config
     *
     * @param AbstractElement $element
     * @return int
     */
    protected function getDaysValue($element)
    {
        $value = $element->getValue();
        
        // If value is a string (serialized), unserialize it
        if (is_string($value) && !empty($value)) {
            try {
                $value = $this->serializer->unserialize($value);
            } catch (\Exception $e) {
                $value = [];
            }
        }
        
        if (is_array($value) && isset($value['days'])) {
            return (int)$value['days'];
        }
        return 1; // Default to 1 day
    }
}
