<?php
namespace Fiserv\Payments\Api;

interface CheckoutOrderInterface
{
    /**
     * Create a checkout order
     * @param string $commercehubOrderId
     * @param string $action
     * @param float|null $amount
     * @param string|null $currency
     * @return array
     */
    public function execute($commercehubOrderId, $action, $amount = null, $currency = null);
}