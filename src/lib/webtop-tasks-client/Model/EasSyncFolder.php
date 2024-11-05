<?php
/**
 * EasSyncFolder
 *
 * PHP version 5
 *
 * @category Class
 * @package  WT\Client\Tasks
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * WebTop Tasks
 *
 * This is Task service API enpoint.
 *
 * OpenAPI spec version: v2
 * Contact: dev-team@sonicle.com
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 3.0.62
 */
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace WT\Client\Tasks\Model;

use \ArrayAccess;
use \WT\Client\Tasks\ObjectSerializer;

/**
 * EasSyncFolder Class Doc Comment
 *
 * @category Class
 * @description Carry task category’s fields.
 * @package  WT\Client\Tasks
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class EasSyncFolder implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'EasSyncFolder';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'id' => 'string',
        'displayName' => 'string',
        'etag' => 'string',
        'deflt' => 'bool',
        'foAcl' => 'string',
        'elAcl' => 'string',
        'ownerId' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'id' => null,
        'displayName' => null,
        'etag' => null,
        'deflt' => null,
        'foAcl' => null,
        'elAcl' => null,
        'ownerId' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'id' => 'id',
        'displayName' => 'displayName',
        'etag' => 'etag',
        'deflt' => 'deflt',
        'foAcl' => 'foAcl',
        'elAcl' => 'elAcl',
        'ownerId' => 'ownerId'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'id' => 'setId',
        'displayName' => 'setDisplayName',
        'etag' => 'setEtag',
        'deflt' => 'setDeflt',
        'foAcl' => 'setFoAcl',
        'elAcl' => 'setElAcl',
        'ownerId' => 'setOwnerId'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'id' => 'getId',
        'displayName' => 'getDisplayName',
        'etag' => 'getEtag',
        'deflt' => 'getDeflt',
        'foAcl' => 'getFoAcl',
        'elAcl' => 'getElAcl',
        'ownerId' => 'getOwnerId'
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
        return self::$swaggerModelName;
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
        $this->container['id'] = isset($data['id']) ? $data['id'] : null;
        $this->container['displayName'] = isset($data['displayName']) ? $data['displayName'] : null;
        $this->container['etag'] = isset($data['etag']) ? $data['etag'] : null;
        $this->container['deflt'] = isset($data['deflt']) ? $data['deflt'] : null;
        $this->container['foAcl'] = isset($data['foAcl']) ? $data['foAcl'] : null;
        $this->container['elAcl'] = isset($data['elAcl']) ? $data['elAcl'] : null;
        $this->container['ownerId'] = isset($data['ownerId']) ? $data['ownerId'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['id'] === null) {
            $invalidProperties[] = "'id' can't be null";
        }
        if ($this->container['displayName'] === null) {
            $invalidProperties[] = "'displayName' can't be null";
        }
        if ($this->container['etag'] === null) {
            $invalidProperties[] = "'etag' can't be null";
        }
        if ($this->container['deflt'] === null) {
            $invalidProperties[] = "'deflt' can't be null";
        }
        if ($this->container['foAcl'] === null) {
            $invalidProperties[] = "'foAcl' can't be null";
        }
        if ($this->container['elAcl'] === null) {
            $invalidProperties[] = "'elAcl' can't be null";
        }
        if ($this->container['ownerId'] === null) {
            $invalidProperties[] = "'ownerId' can't be null";
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
     * Gets id
     *
     * @return string
     */
    public function getId()
    {
        return $this->container['id'];
    }

    /**
     * Sets id
     *
     * @param string $id Category ID (internal)
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->container['id'] = $id;

        return $this;
    }

    /**
     * Gets displayName
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->container['displayName'];
    }

    /**
     * Sets displayName
     *
     * @param string $displayName Display name
     *
     * @return $this
     */
    public function setDisplayName($displayName)
    {
        $this->container['displayName'] = $displayName;

        return $this;
    }

    /**
     * Gets etag
     *
     * @return string
     */
    public function getEtag()
    {
        return $this->container['etag'];
    }

    /**
     * Sets etag
     *
     * @param string $etag Revision tag
     *
     * @return $this
     */
    public function setEtag($etag)
    {
        $this->container['etag'] = $etag;

        return $this;
    }

    /**
     * Gets deflt
     *
     * @return bool
     */
    public function getDeflt()
    {
        return $this->container['deflt'];
    }

    /**
     * Sets deflt
     *
     * @param bool $deflt Specifies if marked as predefined folder
     *
     * @return $this
     */
    public function setDeflt($deflt)
    {
        $this->container['deflt'] = $deflt;

        return $this;
    }

    /**
     * Gets foAcl
     *
     * @return string
     */
    public function getFoAcl()
    {
        return $this->container['foAcl'];
    }

    /**
     * Sets foAcl
     *
     * @param string $foAcl ACL info for folder itself
     *
     * @return $this
     */
    public function setFoAcl($foAcl)
    {
        $this->container['foAcl'] = $foAcl;

        return $this;
    }

    /**
     * Gets elAcl
     *
     * @return string
     */
    public function getElAcl()
    {
        return $this->container['elAcl'];
    }

    /**
     * Sets elAcl
     *
     * @param string $elAcl ACL info for folder elements
     *
     * @return $this
     */
    public function setElAcl($elAcl)
    {
        $this->container['elAcl'] = $elAcl;

        return $this;
    }

    /**
     * Gets ownerId
     *
     * @return string
     */
    public function getOwnerId()
    {
        return $this->container['ownerId'];
    }

    /**
     * Sets ownerId
     *
     * @param string $ownerId The owner profile ID
     *
     * @return $this
     */
    public function setOwnerId($ownerId)
    {
        $this->container['ownerId'] = $ownerId;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
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
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}