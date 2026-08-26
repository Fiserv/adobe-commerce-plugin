<?php
namespace Fiserv\Payments\Plugin\Grid;

class SetLayoutPlugin
{
    /**
     * After setLayout plugin: attach manage payment links block to Sales Order grid or dashboard grids
     *
     * @param \Magento\Framework\View\Element\AbstractBlock $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterSetLayout(\Magento\Framework\View\Element\AbstractBlock $subject, $result)
    {
        try {
            // If this is the Dashboard Grids (tabs) class, add a new tab with our Manage block
            if ($subject instanceof \Magento\Backend\Block\Dashboard\Grids) {
                $layout = $subject->getLayout();
                if ($layout) {
                    $manageBlock = $layout->createBlock(\Fiserv\Payments\Block\Adminhtml\Paybylink\Manage::class);
                    if ($manageBlock) {
                        try {
                            $subject->addTab(
                                'fiserv_manage_payment_links',
                                [
                                    'label' => __('Manage Payment Links'),
                                    'title' => __('Manage Payment Links'),
                                    'content' => $manageBlock->toHtml()
                                ]
                            );
                        } catch (\Throwable $e) {
                            // ignore addTab failures
                        }
                    }
                }

                return $result;
            }

            // Otherwise fall back to attaching as a child for other grids (e.g., sales_order_grid)
            $name = (string)$subject->getNameInLayout();
            if ($name === 'sales_order_grid' || strpos($name, 'dashboard') !== false) {
                $layout = $subject->getLayout();
                if ($layout) {
                    $blockName = 'fiserv_manage_payment_links_grid_child';
                    if (!$layout->getBlock($blockName)) {
                        $manageBlock = $layout->createBlock(
                            \Fiserv\Payments\Block\Adminhtml\Paybylink\Manage::class,
                            $blockName
                        );
                        if ($manageBlock) {
                            $subject->setChild($blockName, $blockName);
                            $layout->setChild($name, $blockName, $blockName);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // swallow to avoid breaking grid rendering
        }

        return $result;
    }

    /**
     * After toHtml plugin: prepend manage block HTML into the sales order grid output
     *
    * @param \Magento\Framework\View\Element\AbstractBlock $subject
    * @param string $result
    * @return string
     */
    public function afterToHtml(\Magento\Framework\View\Element\AbstractBlock $subject, $result)
    {
        try {
            if ($subject->getNameInLayout() === 'sales_order_grid') {
                $layout = $subject->getLayout();
                if ($layout && $layout->getBlock('fiserv_manage_payment_links_grid_child')) {
                    $manageHtml = $layout->getBlock('fiserv_manage_payment_links_grid_child')->toHtml();
                    // prepend the manage block HTML so it appears above the grid
                    return $manageHtml . $result;
                }
            }
        } catch (\Exception $e) {
            // swallow errors to avoid breaking grid rendering
        }

        return $result;
    }
}
