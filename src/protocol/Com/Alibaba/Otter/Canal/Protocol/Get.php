<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: CanalProtocol.proto

namespace Com\Alibaba\Otter\Canal\Protocol;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *  PullRequest
 *
 * Generated from protobuf message <code>com.alibaba.otter.canal.protocol.Get</code>
 */
final class Get extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string destination = 1;</code>
     */
    private $destination = '';
    /**
     * Generated from protobuf field <code>string client_id = 2;</code>
     */
    private $client_id = '';
    /**
     * Generated from protobuf field <code>int32 fetch_size = 3;</code>
     */
    private $fetch_size = 0;
    protected $timeout_present;
    protected $unit_present;
    protected $auto_ack_present;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $destination
     *     @type string $client_id
     *     @type int $fetch_size
     *     @type int|string $timeout
     *           默认-1时代表不控制
     *     @type int $unit
     *           数字类型，0:纳秒,1:毫秒,2:微秒,3:秒,4:分钟,5:小时,6:天
     *     @type bool $auto_ack
     *           是否自动ack
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\CanalProtocol::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string destination = 1;</code>
     * @return string
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Generated from protobuf field <code>string destination = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setDestination($var)
    {
        GPBUtil::checkString($var, True);
        $this->destination = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string client_id = 2;</code>
     * @return string
     */
    public function getClientId()
    {
        return $this->client_id;
    }

    /**
     * Generated from protobuf field <code>string client_id = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setClientId($var)
    {
        GPBUtil::checkString($var, True);
        $this->client_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 fetch_size = 3;</code>
     * @return int
     */
    public function getFetchSize()
    {
        return $this->fetch_size;
    }

    /**
     * Generated from protobuf field <code>int32 fetch_size = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setFetchSize($var)
    {
        GPBUtil::checkInt32($var);
        $this->fetch_size = $var;

        return $this;
    }

    /**
     * 默认-1时代表不控制
     *
     * Generated from protobuf field <code>int64 timeout = 4;</code>
     * @return int|string
     */
    public function getTimeout()
    {
        return $this->readOneof(4);
    }

    /**
     * 默认-1时代表不控制
     *
     * Generated from protobuf field <code>int64 timeout = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setTimeout($var)
    {
        GPBUtil::checkInt64($var);
        $this->writeOneof(4, $var);

        return $this;
    }

    /**
     * 数字类型，0:纳秒,1:毫秒,2:微秒,3:秒,4:分钟,5:小时,6:天
     *
     * Generated from protobuf field <code>int32 unit = 5;</code>
     * @return int
     */
    public function getUnit()
    {
        return $this->readOneof(5);
    }

    /**
     * 数字类型，0:纳秒,1:毫秒,2:微秒,3:秒,4:分钟,5:小时,6:天
     *
     * Generated from protobuf field <code>int32 unit = 5;</code>
     * @param int $var
     * @return $this
     */
    public function setUnit($var)
    {
        GPBUtil::checkInt32($var);
        $this->writeOneof(5, $var);

        return $this;
    }

    /**
     * 是否自动ack
     *
     * Generated from protobuf field <code>bool auto_ack = 6;</code>
     * @return bool
     */
    public function getAutoAck()
    {
        return $this->readOneof(6);
    }

    /**
     * 是否自动ack
     *
     * Generated from protobuf field <code>bool auto_ack = 6;</code>
     * @param bool $var
     * @return $this
     */
    public function setAutoAck($var)
    {
        GPBUtil::checkBool($var);
        $this->writeOneof(6, $var);

        return $this;
    }

    /**
     * @return string
     */
    public function getTimeoutPresent()
    {
        return $this->whichOneof("timeout_present");
    }

    /**
     * @return string
     */
    public function getUnitPresent()
    {
        return $this->whichOneof("unit_present");
    }

    /**
     * @return string
     */
    public function getAutoAckPresent()
    {
        return $this->whichOneof("auto_ack_present");
    }

}
