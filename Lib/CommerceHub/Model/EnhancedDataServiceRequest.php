<?php
/**
 * EnhancedDataServiceRequest
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * Payments
 *
 * This API Specification is designed to provide technical guidance required to consume and integrate with our APIs for payment processing.
 *
 * The version of the OpenAPI document: v1.3.0
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 6.2.1
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace Fiserv\Payments\Lib\CommerceHub\Model;

use \ArrayAccess;
use \Fiserv\Payments\Lib\CommerceHub\ObjectSerializer;

/**
 * EnhancedDataServiceRequest Class Doc Comment
 *
 * @category Class
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class EnhancedDataServiceRequest implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'EnhancedDataServiceRequest';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'amount' => '\Fiserv\Payments\Lib\CommerceHub\Model\Amount',
        'source' => '\Fiserv\Payments\Lib\CommerceHub\Model\Source',
        'transaction_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails',
        'dynamic_descriptors' => '\Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors',
        'billing_address' => '\Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress',
        'shipping_address' => '\Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress',
        'merchant_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails',
        'customer' => '\Fiserv\Payments\Lib\CommerceHub\Model\Customer',
        'fraud_attributes' => '\Fiserv\Payments\Lib\CommerceHub\Model\FraudAttributes',
        'stored_credentials' => '\Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials',
        'transaction_interaction' => '\Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction',
        'additional_data_common' => '\Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon',
        'order_data' => '\Fiserv\Payments\Lib\CommerceHub\Model\OrderData',
        'split_tender' => '\Fiserv\Payments\Lib\CommerceHub\Model\SplitTender'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'amount' => null,
        'source' => null,
        'transaction_details' => null,
        'dynamic_descriptors' => null,
        'billing_address' => null,
        'shipping_address' => null,
        'merchant_details' => null,
        'customer' => null,
        'fraud_attributes' => null,
        'stored_credentials' => null,
        'transaction_interaction' => null,
        'additional_data_common' => null,
        'order_data' => null,
        'split_tender' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'amount' => false,
		'source' => false,
		'transaction_details' => false,
		'dynamic_descriptors' => false,
		'billing_address' => false,
		'shipping_address' => false,
		'merchant_details' => false,
		'customer' => false,
		'fraud_attributes' => false,
		'stored_credentials' => false,
		'transaction_interaction' => false,
		'additional_data_common' => false,
		'order_data' => false,
		'split_tender' => false
    ];

    /**
      * If a nullable field gets set to null, insert it here
      *
      * @var boolean[]
      */
    protected array $openAPINullablesSetToNull = [];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPITypes()
    {
        return self::$openAPITypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function openAPIFormats()
    {
        return self::$openAPIFormats;
    }

    /**
     * Array of nullable properties
     *
     * @return array
     */
    protected static function openAPINullables(): array
    {
        return self::$openAPINullables;
    }

    /**
     * Array of nullable field names deliberately set to null
     *
     * @return boolean[]
     */
    private function getOpenAPINullablesSetToNull(): array
    {
        return $this->openAPINullablesSetToNull;
    }

    /**
     * Setter - Array of nullable field names deliberately set to null
     *
     * @param boolean[] $openAPINullablesSetToNull
     */
    private function setOpenAPINullablesSetToNull(array $openAPINullablesSetToNull): void
    {
        $this->openAPINullablesSetToNull = $openAPINullablesSetToNull;
    }

    /**
     * Checks if a property is nullable
     *
     * @param string $property
     * @return bool
     */
    public static function isNullable(string $property): bool
    {
        return self::openAPINullables()[$property] ?? false;
    }

    /**
     * Checks if a nullable property is set to null.
     *
     * @param string $property
     * @return bool
     */
    public function isNullableSetToNull(string $property): bool
    {
        return in_array($property, $this->getOpenAPINullablesSetToNull(), true);
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'amount' => 'amount',
        'source' => 'source',
        'transaction_details' => 'transactionDetails',
        'dynamic_descriptors' => 'dynamicDescriptors',
        'billing_address' => 'billingAddress',
        'shipping_address' => 'shippingAddress',
        'merchant_details' => 'merchantDetails',
        'customer' => 'customer',
        'fraud_attributes' => 'fraudAttributes',
        'stored_credentials' => 'storedCredentials',
        'transaction_interaction' => 'transactionInteraction',
        'additional_data_common' => 'additionalDataCommon',
        'order_data' => 'orderData',
        'split_tender' => 'splitTender'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'amount' => 'setAmount',
        'source' => 'setSource',
        'transaction_details' => 'setTransactionDetails',
        'dynamic_descriptors' => 'setDynamicDescriptors',
        'billing_address' => 'setBillingAddress',
        'shipping_address' => 'setShippingAddress',
        'merchant_details' => 'setMerchantDetails',
        'customer' => 'setCustomer',
        'fraud_attributes' => 'setFraudAttributes',
        'stored_credentials' => 'setStoredCredentials',
        'transaction_interaction' => 'setTransactionInteraction',
        'additional_data_common' => 'setAdditionalDataCommon',
        'order_data' => 'setOrderData',
        'split_tender' => 'setSplitTender'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'amount' => 'getAmount',
        'source' => 'getSource',
        'transaction_details' => 'getTransactionDetails',
        'dynamic_descriptors' => 'getDynamicDescriptors',
        'billing_address' => 'getBillingAddress',
        'shipping_address' => 'getShippingAddress',
        'merchant_details' => 'getMerchantDetails',
        'customer' => 'getCustomer',
        'fraud_attributes' => 'getFraudAttributes',
        'stored_credentials' => 'getStoredCredentials',
        'transaction_interaction' => 'getTransactionInteraction',
        'additional_data_common' => 'getAdditionalDataCommon',
        'order_data' => 'getOrderData',
        'split_tender' => 'getSplitTender'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    public function getModelName()
    {
        return self::$openAPIModelName;
    }


    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->setIfExists('amount', $data ?? [], null);
        $this->setIfExists('source', $data ?? [], null);
        $this->setIfExists('transaction_details', $data ?? [], null);
        $this->setIfExists('dynamic_descriptors', $data ?? [], null);
        $this->setIfExists('billing_address', $data ?? [], null);
        $this->setIfExists('shipping_address', $data ?? [], null);
        $this->setIfExists('merchant_details', $data ?? [], null);
        $this->setIfExists('customer', $data ?? [], null);
        $this->setIfExists('fraud_attributes', $data ?? [], null);
        $this->setIfExists('stored_credentials', $data ?? [], null);
        $this->setIfExists('transaction_interaction', $data ?? [], null);
        $this->setIfExists('additional_data_common', $data ?? [], null);
        $this->setIfExists('order_data', $data ?? [], null);
        $this->setIfExists('split_tender', $data ?? [], null);
    }

    /**
    * Sets $this->container[$variableName] to the given data or to the given default Value; if $variableName
    * is nullable and its value is set to null in the $fields array, then mark it as "set to null" in the
    * $this->openAPINullablesSetToNull array
    *
    * @param string $variableName
    * @param array  $fields
    * @param mixed  $defaultValue
    */
    private function setIfExists(string $variableName, array $fields, $defaultValue): void
    {
        if (self::isNullable($variableName) && array_key_exists($variableName, $fields) && is_null($fields[$variableName])) {
            $this->openAPINullablesSetToNull[] = $variableName;
        }

        $this->container[$variableName] = $fields[$variableName] ?? $defaultValue;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    public function valid()
    {
        return count($this->listInvalidProperties()) === 0;
    }


    /**
     * Gets amount
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Amount|null
     */
    public function getAmount()
    {
        return $this->container['amount'];
    }

    /**
     * Sets amount
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Amount|null $amount amount
     *
     * @return self
     */
    public function setAmount($amount)
    {

        if (is_null($amount)) {
            throw new \InvalidArgumentException('non-nullable amount cannot be null');
        }

        $this->container['amount'] = $amount;

        return $this;
    }

    /**
     * Gets source
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Source|null
     */
    public function getSource()
    {
        return $this->container['source'];
    }

    /**
     * Sets source
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Source|null $source source
     *
     * @return self
     */
    public function setSource($source)
    {

        if (is_null($source)) {
            throw new \InvalidArgumentException('non-nullable source cannot be null');
        }

        $this->container['source'] = $source;

        return $this;
    }

    /**
     * Gets transaction_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails|null
     */
    public function getTransactionDetails()
    {
        return $this->container['transaction_details'];
    }

    /**
     * Sets transaction_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\TransactionDetails|null $transaction_details transaction_details
     *
     * @return self
     */
    public function setTransactionDetails($transaction_details)
    {

        if (is_null($transaction_details)) {
            throw new \InvalidArgumentException('non-nullable transaction_details cannot be null');
        }

        $this->container['transaction_details'] = $transaction_details;

        return $this;
    }

    /**
     * Gets dynamic_descriptors
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors|null
     */
    public function getDynamicDescriptors()
    {
        return $this->container['dynamic_descriptors'];
    }

    /**
     * Sets dynamic_descriptors
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\DynamicDescriptors|null $dynamic_descriptors dynamic_descriptors
     *
     * @return self
     */
    public function setDynamicDescriptors($dynamic_descriptors)
    {

        if (is_null($dynamic_descriptors)) {
            throw new \InvalidArgumentException('non-nullable dynamic_descriptors cannot be null');
        }

        $this->container['dynamic_descriptors'] = $dynamic_descriptors;

        return $this;
    }

    /**
     * Gets billing_address
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress|null
     */
    public function getBillingAddress()
    {
        return $this->container['billing_address'];
    }

    /**
     * Sets billing_address
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\BillingAddress|null $billing_address billing_address
     *
     * @return self
     */
    public function setBillingAddress($billing_address)
    {

        if (is_null($billing_address)) {
            throw new \InvalidArgumentException('non-nullable billing_address cannot be null');
        }

        $this->container['billing_address'] = $billing_address;

        return $this;
    }

    /**
     * Gets shipping_address
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress|null
     */
    public function getShippingAddress()
    {
        return $this->container['shipping_address'];
    }

    /**
     * Sets shipping_address
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ShippingAddress|null $shipping_address shipping_address
     *
     * @return self
     */
    public function setShippingAddress($shipping_address)
    {

        if (is_null($shipping_address)) {
            throw new \InvalidArgumentException('non-nullable shipping_address cannot be null');
        }

        $this->container['shipping_address'] = $shipping_address;

        return $this;
    }

    /**
     * Gets merchant_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails|null
     */
    public function getMerchantDetails()
    {
        return $this->container['merchant_details'];
    }

    /**
     * Sets merchant_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\MerchantDetails|null $merchant_details merchant_details
     *
     * @return self
     */
    public function setMerchantDetails($merchant_details)
    {

        if (is_null($merchant_details)) {
            throw new \InvalidArgumentException('non-nullable merchant_details cannot be null');
        }

        $this->container['merchant_details'] = $merchant_details;

        return $this;
    }

    /**
     * Gets customer
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Customer|null
     */
    public function getCustomer()
    {
        return $this->container['customer'];
    }

    /**
     * Sets customer
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Customer|null $customer customer
     *
     * @return self
     */
    public function setCustomer($customer)
    {

        if (is_null($customer)) {
            throw new \InvalidArgumentException('non-nullable customer cannot be null');
        }

        $this->container['customer'] = $customer;

        return $this;
    }

    /**
     * Gets fraud_attributes
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\FraudAttributes|null
     */
    public function getFraudAttributes()
    {
        return $this->container['fraud_attributes'];
    }

    /**
     * Sets fraud_attributes
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\FraudAttributes|null $fraud_attributes fraud_attributes
     *
     * @return self
     */
    public function setFraudAttributes($fraud_attributes)
    {

        if (is_null($fraud_attributes)) {
            throw new \InvalidArgumentException('non-nullable fraud_attributes cannot be null');
        }

        $this->container['fraud_attributes'] = $fraud_attributes;

        return $this;
    }

    /**
     * Gets stored_credentials
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials|null
     */
    public function getStoredCredentials()
    {
        return $this->container['stored_credentials'];
    }

    /**
     * Sets stored_credentials
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\StoredCredentials|null $stored_credentials stored_credentials
     *
     * @return self
     */
    public function setStoredCredentials($stored_credentials)
    {

        if (is_null($stored_credentials)) {
            throw new \InvalidArgumentException('non-nullable stored_credentials cannot be null');
        }

        $this->container['stored_credentials'] = $stored_credentials;

        return $this;
    }

    /**
     * Gets transaction_interaction
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction|null
     */
    public function getTransactionInteraction()
    {
        return $this->container['transaction_interaction'];
    }

    /**
     * Sets transaction_interaction
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\TransactionInteraction|null $transaction_interaction transaction_interaction
     *
     * @return self
     */
    public function setTransactionInteraction($transaction_interaction)
    {

        if (is_null($transaction_interaction)) {
            throw new \InvalidArgumentException('non-nullable transaction_interaction cannot be null');
        }

        $this->container['transaction_interaction'] = $transaction_interaction;

        return $this;
    }

    /**
     * Gets additional_data_common
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon|null
     */
    public function getAdditionalDataCommon()
    {
        return $this->container['additional_data_common'];
    }

    /**
     * Sets additional_data_common
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\AdditionalDataCommon|null $additional_data_common additional_data_common
     *
     * @return self
     */
    public function setAdditionalDataCommon($additional_data_common)
    {

        if (is_null($additional_data_common)) {
            throw new \InvalidArgumentException('non-nullable additional_data_common cannot be null');
        }

        $this->container['additional_data_common'] = $additional_data_common;

        return $this;
    }

    /**
     * Gets order_data
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\OrderData|null
     */
    public function getOrderData()
    {
        return $this->container['order_data'];
    }

    /**
     * Sets order_data
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\OrderData|null $order_data order_data
     *
     * @return self
     */
    public function setOrderData($order_data)
    {

        if (is_null($order_data)) {
            throw new \InvalidArgumentException('non-nullable order_data cannot be null');
        }

        $this->container['order_data'] = $order_data;

        return $this;
    }

    /**
     * Gets split_tender
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\SplitTender|null
     */
    public function getSplitTender()
    {
        return $this->container['split_tender'];
    }

    /**
     * Sets split_tender
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\SplitTender|null $split_tender split_tender
     *
     * @return self
     */
    public function setSplitTender($split_tender)
    {

        if (is_null($split_tender)) {
            throw new \InvalidArgumentException('non-nullable split_tender cannot be null');
        }

        $this->container['split_tender'] = $split_tender;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->container[$offset] ?? null;
    }

    /**
     * Sets value based on offset.
     *
     * @param int|null $offset Offset
     * @param mixed    $value  Value to be set
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->container[$offset]);
    }

    /**
     * Serializes the object to a value that can be serialized natively by json_encode().
     * @link https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed Returns data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
       return ObjectSerializer::sanitizeForSerialization($this);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode(
            ObjectSerializer::sanitizeForSerialization($this),
            JSON_PRETTY_PRINT
        );
    }

    /**
     * Gets a header-safe presentation of the object
     *
     * @return string
     */
    public function toHeaderValue()
    {
        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


