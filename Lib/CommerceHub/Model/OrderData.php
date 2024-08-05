<?php
/**
 * OrderData
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
 * OrderData Class Doc Comment
 *
 * @category Class
 * @description &lt;a href&#x3D;\&quot;../docs?path&#x3D;docs/Resources/Master-Data/Order-Data.md\&quot;&gt;Order data&lt;/a&gt; can be used for merchant database tracking, improve authorization rates and reduce fraud.
 * @package  Fiserv\Payments\Lib\CommerceHub
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 * @implements \ArrayAccess<string, mixed>
 */
class OrderData implements ModelInterface, ArrayAccess, \JsonSerializable
{
    public const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $openAPIModelName = 'OrderData';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $openAPITypes = [
        'order_date' => '\DateTime',
        'item_count' => 'int',
        'item_details' => '\Fiserv\Payments\Lib\CommerceHub\Model\ItemDetails[]',
        'pre_order' => 'bool',
        'pre_order_date' => '\DateTime',
        're_order' => 'bool',
        'goods_sold_code' => 'string',
        'giftcard_count' => 'int',
        'gift_card_amount' => '\Fiserv\Payments\Lib\CommerceHub\Model\Amount',
        'insurance_claim_number' => 'string',
        'department_code' => 'int',
        'department_sub_code' => 'int',
        'department_class' => 'int',
        'department_sub_class' => 'int'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      * @phpstan-var array<string, string|null>
      * @psalm-var array<string, string|null>
      */
    protected static $openAPIFormats = [
        'order_date' => 'date',
        'item_count' => null,
        'item_details' => null,
        'pre_order' => null,
        'pre_order_date' => 'date',
        're_order' => null,
        'goods_sold_code' => null,
        'giftcard_count' => null,
        'gift_card_amount' => null,
        'insurance_claim_number' => null,
        'department_code' => null,
        'department_sub_code' => null,
        'department_class' => null,
        'department_sub_class' => null
    ];

    /**
      * Array of nullable properties. Used for (de)serialization
      *
      * @var boolean[]
      */
    protected static array $openAPINullables = [
        'order_date' => false,
		'item_count' => false,
		'item_details' => false,
		'pre_order' => false,
		'pre_order_date' => false,
		're_order' => false,
		'goods_sold_code' => false,
		'giftcard_count' => false,
		'gift_card_amount' => false,
		'insurance_claim_number' => false,
		'department_code' => false,
		'department_sub_code' => false,
		'department_class' => false,
		'department_sub_class' => false
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
        'order_date' => 'orderDate',
        'item_count' => 'itemCount',
        'item_details' => 'itemDetails',
        'pre_order' => 'preOrder',
        'pre_order_date' => 'preOrderDate',
        're_order' => 'reOrder',
        'goods_sold_code' => 'goodsSoldCode',
        'giftcard_count' => 'giftcardCount',
        'gift_card_amount' => 'giftCardAmount',
        'insurance_claim_number' => 'insuranceClaimNumber',
        'department_code' => 'departmentCode',
        'department_sub_code' => 'departmentSubCode',
        'department_class' => 'departmentClass',
        'department_sub_class' => 'departmentSubClass'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'order_date' => 'setOrderDate',
        'item_count' => 'setItemCount',
        'item_details' => 'setItemDetails',
        'pre_order' => 'setPreOrder',
        'pre_order_date' => 'setPreOrderDate',
        're_order' => 'setReOrder',
        'goods_sold_code' => 'setGoodsSoldCode',
        'giftcard_count' => 'setGiftcardCount',
        'gift_card_amount' => 'setGiftCardAmount',
        'insurance_claim_number' => 'setInsuranceClaimNumber',
        'department_code' => 'setDepartmentCode',
        'department_sub_code' => 'setDepartmentSubCode',
        'department_class' => 'setDepartmentClass',
        'department_sub_class' => 'setDepartmentSubClass'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'order_date' => 'getOrderDate',
        'item_count' => 'getItemCount',
        'item_details' => 'getItemDetails',
        'pre_order' => 'getPreOrder',
        'pre_order_date' => 'getPreOrderDate',
        're_order' => 'getReOrder',
        'goods_sold_code' => 'getGoodsSoldCode',
        'giftcard_count' => 'getGiftcardCount',
        'gift_card_amount' => 'getGiftCardAmount',
        'insurance_claim_number' => 'getInsuranceClaimNumber',
        'department_code' => 'getDepartmentCode',
        'department_sub_code' => 'getDepartmentSubCode',
        'department_class' => 'getDepartmentClass',
        'department_sub_class' => 'getDepartmentSubClass'
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
        $this->setIfExists('order_date', $data ?? [], null);
        $this->setIfExists('item_count', $data ?? [], null);
        $this->setIfExists('item_details', $data ?? [], null);
        $this->setIfExists('pre_order', $data ?? [], null);
        $this->setIfExists('pre_order_date', $data ?? [], null);
        $this->setIfExists('re_order', $data ?? [], null);
        $this->setIfExists('goods_sold_code', $data ?? [], null);
        $this->setIfExists('giftcard_count', $data ?? [], null);
        $this->setIfExists('gift_card_amount', $data ?? [], null);
        $this->setIfExists('insurance_claim_number', $data ?? [], null);
        $this->setIfExists('department_code', $data ?? [], null);
        $this->setIfExists('department_sub_code', $data ?? [], null);
        $this->setIfExists('department_class', $data ?? [], null);
        $this->setIfExists('department_sub_class', $data ?? [], null);
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

        if (!is_null($this->container['order_date']) && (mb_strlen($this->container['order_date']) > 10)) {
            $invalidProperties[] = "invalid value for 'order_date', the character length must be smaller than or equal to 10.";
        }

        if (!is_null($this->container['pre_order_date']) && (mb_strlen($this->container['pre_order_date']) > 10)) {
            $invalidProperties[] = "invalid value for 'pre_order_date', the character length must be smaller than or equal to 10.";
        }

        if (!is_null($this->container['goods_sold_code']) && (mb_strlen($this->container['goods_sold_code']) > 16)) {
            $invalidProperties[] = "invalid value for 'goods_sold_code', the character length must be smaller than or equal to 16.";
        }

        if (!is_null($this->container['giftcard_count']) && ($this->container['giftcard_count'] > 99)) {
            $invalidProperties[] = "invalid value for 'giftcard_count', must be smaller than or equal to 99.";
        }

        if (!is_null($this->container['insurance_claim_number']) && (mb_strlen($this->container['insurance_claim_number']) > 5)) {
            $invalidProperties[] = "invalid value for 'insurance_claim_number', the character length must be smaller than or equal to 5.";
        }

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
     * Gets order_date
     *
     * @return \DateTime|null
     */
    public function getOrderDate()
    {
        return $this->container['order_date'];
    }

    /**
     * Sets order_date
     *
     * @param \DateTime|null $order_date Date that goods and services are ordered, in YYYY-MM-DD format.
     *
     * @return self
     */
    public function setOrderDate($order_date)
    {
        if (!is_null($order_date) && (mb_strlen($order_date) > 10)) {
            throw new \InvalidArgumentException('invalid length for $order_date when calling OrderData., must be smaller than or equal to 10.');
        }


        if (is_null($order_date)) {
            throw new \InvalidArgumentException('non-nullable order_date cannot be null');
        }

        $this->container['order_date'] = $order_date;

        return $this;
    }

    /**
     * Gets item_count
     *
     * @return int|null
     */
    public function getItemCount()
    {
        return $this->container['item_count'];
    }

    /**
     * Sets item_count
     *
     * @param int|null $item_count Total number of items included in the purchase.
     *
     * @return self
     */
    public function setItemCount($item_count)
    {

        if (is_null($item_count)) {
            throw new \InvalidArgumentException('non-nullable item_count cannot be null');
        }

        $this->container['item_count'] = $item_count;

        return $this;
    }

    /**
     * Gets item_details
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\ItemDetails[]|null
     */
    public function getItemDetails()
    {
        return $this->container['item_details'];
    }

    /**
     * Sets item_details
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\ItemDetails[]|null $item_details item_details
     *
     * @return self
     */
    public function setItemDetails($item_details)
    {

        if (is_null($item_details)) {
            throw new \InvalidArgumentException('non-nullable item_details cannot be null');
        }

        $this->container['item_details'] = $item_details;

        return $this;
    }

    /**
     * Gets pre_order
     *
     * @return bool|null
     */
    public function getPreOrder()
    {
        return $this->container['pre_order'];
    }

    /**
     * Sets pre_order
     *
     * @param bool|null $pre_order Identifies if the purchase is a preorder.
     *
     * @return self
     */
    public function setPreOrder($pre_order)
    {

        if (is_null($pre_order)) {
            throw new \InvalidArgumentException('non-nullable pre_order cannot be null');
        }

        $this->container['pre_order'] = $pre_order;

        return $this;
    }

    /**
     * Gets pre_order_date
     *
     * @return \DateTime|null
     */
    public function getPreOrderDate()
    {
        return $this->container['pre_order_date'];
    }

    /**
     * Sets pre_order_date
     *
     * @param \DateTime|null $pre_order_date Date that goods and services are pre-ordered, in YYYY-MM-DD format.
     *
     * @return self
     */
    public function setPreOrderDate($pre_order_date)
    {
        if (!is_null($pre_order_date) && (mb_strlen($pre_order_date) > 10)) {
            throw new \InvalidArgumentException('invalid length for $pre_order_date when calling OrderData., must be smaller than or equal to 10.');
        }


        if (is_null($pre_order_date)) {
            throw new \InvalidArgumentException('non-nullable pre_order_date cannot be null');
        }

        $this->container['pre_order_date'] = $pre_order_date;

        return $this;
    }

    /**
     * Gets re_order
     *
     * @return bool|null
     */
    public function getReOrder()
    {
        return $this->container['re_order'];
    }

    /**
     * Sets re_order
     *
     * @param bool|null $re_order Identifies if the purchase is a reorder.
     *
     * @return self
     */
    public function setReOrder($re_order)
    {

        if (is_null($re_order)) {
            throw new \InvalidArgumentException('non-nullable re_order cannot be null');
        }

        $this->container['re_order'] = $re_order;

        return $this;
    }

    /**
     * Gets goods_sold_code
     *
     * @return string|null
     */
    public function getGoodsSoldCode()
    {
        return $this->container['goods_sold_code'];
    }

    /**
     * Sets goods_sold_code
     *
     * @param string|null $goods_sold_code Indicates a specific type of goods. It is used to help identify potentially fraudulent sales.
     *
     * @return self
     */
    public function setGoodsSoldCode($goods_sold_code)
    {
        if (!is_null($goods_sold_code) && (mb_strlen($goods_sold_code) > 16)) {
            throw new \InvalidArgumentException('invalid length for $goods_sold_code when calling OrderData., must be smaller than or equal to 16.');
        }


        if (is_null($goods_sold_code)) {
            throw new \InvalidArgumentException('non-nullable goods_sold_code cannot be null');
        }

        $this->container['goods_sold_code'] = $goods_sold_code;

        return $this;
    }

    /**
     * Gets giftcard_count
     *
     * @return int|null
     */
    public function getGiftcardCount()
    {
        return $this->container['giftcard_count'];
    }

    /**
     * Sets giftcard_count
     *
     * @param int|null $giftcard_count Total number of gift cards purchased.
     *
     * @return self
     */
    public function setGiftcardCount($giftcard_count)
    {

        if (!is_null($giftcard_count) && ($giftcard_count > 99)) {
            throw new \InvalidArgumentException('invalid value for $giftcard_count when calling OrderData., must be smaller than or equal to 99.');
        }


        if (is_null($giftcard_count)) {
            throw new \InvalidArgumentException('non-nullable giftcard_count cannot be null');
        }

        $this->container['giftcard_count'] = $giftcard_count;

        return $this;
    }

    /**
     * Gets gift_card_amount
     *
     * @return \Fiserv\Payments\Lib\CommerceHub\Model\Amount|null
     */
    public function getGiftCardAmount()
    {
        return $this->container['gift_card_amount'];
    }

    /**
     * Sets gift_card_amount
     *
     * @param \Fiserv\Payments\Lib\CommerceHub\Model\Amount|null $gift_card_amount gift_card_amount
     *
     * @return self
     */
    public function setGiftCardAmount($gift_card_amount)
    {

        if (is_null($gift_card_amount)) {
            throw new \InvalidArgumentException('non-nullable gift_card_amount cannot be null');
        }

        $this->container['gift_card_amount'] = $gift_card_amount;

        return $this;
    }

    /**
     * Gets insurance_claim_number
     *
     * @return string|null
     */
    public function getInsuranceClaimNumber()
    {
        return $this->container['insurance_claim_number'];
    }

    /**
     * Sets insurance_claim_number
     *
     * @param string|null $insurance_claim_number The Insurance Claim Number of the Customer.
     *
     * @return self
     */
    public function setInsuranceClaimNumber($insurance_claim_number)
    {
        if (!is_null($insurance_claim_number) && (mb_strlen($insurance_claim_number) > 5)) {
            throw new \InvalidArgumentException('invalid length for $insurance_claim_number when calling OrderData., must be smaller than or equal to 5.');
        }


        if (is_null($insurance_claim_number)) {
            throw new \InvalidArgumentException('non-nullable insurance_claim_number cannot be null');
        }

        $this->container['insurance_claim_number'] = $insurance_claim_number;

        return $this;
    }

    /**
     * Gets department_code
     *
     * @return int|null
     */
    public function getDepartmentCode()
    {
        return $this->container['department_code'];
    }

    /**
     * Sets department_code
     *
     * @param int|null $department_code Merchant defined code identifying the department the item was purchased.
     *
     * @return self
     */
    public function setDepartmentCode($department_code)
    {



        if (is_null($department_code)) {
            throw new \InvalidArgumentException('non-nullable department_code cannot be null');
        }

        $this->container['department_code'] = $department_code;

        return $this;
    }

    /**
     * Gets department_sub_code
     *
     * @return int|null
     */
    public function getDepartmentSubCode()
    {
        return $this->container['department_sub_code'];
    }

    /**
     * Sets department_sub_code
     *
     * @param int|null $department_sub_code Merchant defined sub code identifying the sub department the item was purchased.
     *
     * @return self
     */
    public function setDepartmentSubCode($department_sub_code)
    {



        if (is_null($department_sub_code)) {
            throw new \InvalidArgumentException('non-nullable department_sub_code cannot be null');
        }

        $this->container['department_sub_code'] = $department_sub_code;

        return $this;
    }

    /**
     * Gets department_class
     *
     * @return int|null
     */
    public function getDepartmentClass()
    {
        return $this->container['department_class'];
    }

    /**
     * Sets department_class
     *
     * @param int|null $department_class Merchant defined code identifying the department class the item was purchased.
     *
     * @return self
     */
    public function setDepartmentClass($department_class)
    {



        if (is_null($department_class)) {
            throw new \InvalidArgumentException('non-nullable department_class cannot be null');
        }

        $this->container['department_class'] = $department_class;

        return $this;
    }

    /**
     * Gets department_sub_class
     *
     * @return int|null
     */
    public function getDepartmentSubClass()
    {
        return $this->container['department_sub_class'];
    }

    /**
     * Sets department_sub_class
     *
     * @param int|null $department_sub_class Merchant defined sub code identifying the department sub class the item was purchased.
     *
     * @return self
     */
    public function setDepartmentSubClass($department_sub_class)
    {



        if (is_null($department_sub_class)) {
            throw new \InvalidArgumentException('non-nullable department_sub_class cannot be null');
        }

        $this->container['department_sub_class'] = $department_sub_class;

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


