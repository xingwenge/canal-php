<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: CanalProtocol.proto

namespace Com\Alibaba\Otter\Canal\Protocol;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>com.alibaba.otter.canal.protocol.ClientRollback</code>
 */
class ClientRollback extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string destination = 1;</code>
     */
    protected $destination = '';
    /**
     * Generated from protobuf field <code>string client_id = 2;</code>
     */
    protected $client_id = '';
    /**
     * Generated from protobuf field <code>int64 batch_id = 3;</code>
     */
    protected $batch_id = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $destination
     *     @type string $client_id
     *     @type int|string $batch_id
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
     * Generated from protobuf field <code>int64 batch_id = 3;</code>
     * @return int|string
     */
    public function getBatchId()
    {
        return $this->batch_id;
    }

    /**
     * Generated from protobuf field <code>int64 batch_id = 3;</code>
     * @param int|string $var
     * @return $this
     */
    public function setBatchId($var)
    {
        GPBUtil::checkInt64($var);
        $this->batch_id = $var;

        return $this;
    }

}

