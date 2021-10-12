<?php
namespace xingwenge\canal_php\adapter;

use Com\Alibaba\Otter\Canal\Protocol\Ack;
use Com\Alibaba\Otter\Canal\Protocol\ClientAck;
use Com\Alibaba\Otter\Canal\Protocol\ClientAuth;
use Com\Alibaba\Otter\Canal\Protocol\ClientRollback;
use Com\Alibaba\Otter\Canal\Protocol\Entry;
use Com\Alibaba\Otter\Canal\Protocol\Get;
use Com\Alibaba\Otter\Canal\Protocol\Handshake;
use Com\Alibaba\Otter\Canal\Protocol\Messages;
use Com\Alibaba\Otter\Canal\Protocol\Packet;
use Com\Alibaba\Otter\Canal\Protocol\PacketType;
use Com\Alibaba\Otter\Canal\Protocol\Sub;
use xingwenge\canal_php\ICanalConnector;
use xingwenge\canal_php\Message;

abstract class CanalConnectorBase implements ICanalConnector
{
    protected $client;
    protected $readTimeout;
    protected $writeTimeout;
    protected $packetLen = 4;

    protected $destination;
    protected $clientId;

    public function __construct()
    {
    }

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        $this->disConnect();
    }

    /**
     * @param string $host
     * @param int $port
     * @param int $connectionTimeout
     * @param int $readTimeout
     * @param int $writeTimeout
     * @throws \Exception
     */
    public function connect($host = "127.0.0.1", $port = 11111, $user = "", $password = "", $connectionTimeout = 10, $readTimeout = 30, $writeTimeout = 30)
    {
        $this->doConnect($host, $port, $connectionTimeout, $readTimeout, $writeTimeout);

        $this->readTimeout = $readTimeout;
        $this->writeTimeout = $writeTimeout;

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);

        // 密码需要通过握手包返回的seed进行哈希
        if ($user && $password) {
            $handShake = new Handshake();
            $handShake->mergeFromString($packet->getBody());
            $password = bin2hex($this->scramble411($password, $handShake->getSeeds()));
        }
        $this->checkValid($user, $password);

        if ($packet->getType() != PacketType::HANDSHAKE) {
            throw new \Exception("connect error.");
        }
    }


    /**
     * @param string $password
     * @param string $seed
     * @return false|string
     */
    public function scramble411($password, $seed)
    {
        $pwd1 = sha1($password, true);
        $pwd2 = sha1($pwd1, true);
        $pwd3 = sha1($seed . $pwd2, true);

        $pwd1Bytes = $this->stringToBytes($pwd1);
        $pwd3Bytes = $this->stringToBytes($pwd3);
        foreach ($pwd3Bytes as $key => $pwd3Byte) {
            $pwd3Bytes[$key] ^= $pwd1Bytes[$key];
        }
        return $this->bytesToString($pwd3Bytes);
    }

    /**
     * @param string $string
     * @return array|false
     */
    public function stringToBytes($string)
    {
        return unpack("C*", $string);
    }

    /**
     * @param array $bytes
     * @return false|string
     */
    public function bytesToString($bytes)
    {
        return pack("C*", ...$bytes);
    }

    public function disConnect()
    {
        if ($this->client) {
            $this->rollback(0);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @throws \Exception
     */
    public function checkValid($username="", $password="")
    {
        $ca = new ClientAuth();
        $ca->setUsername($username);
        $ca->setPassword($password);
        $ca->setNetReadTimeout($this->readTimeout * 1000);
        $ca->setNetWriteTimeout($this->writeTimeout * 1000);

        $packet = new Packet();
        $packet->setType(PacketType::CLIENTAUTHENTICATION);
        $packet->setBody($ca->serializeToString());
        $this->writeWithHeader($packet->serializeToString());

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);
        if ($packet->getType() != PacketType::ACK) {
            throw new \Exception("Auth error.");
        }
        $ack = new Ack();
        $ack->mergeFromString($packet->getBody());
        if ($ack->getErrorCode() > 0) {
            throw new \Exception(sprintf("something goes wrong when doing authentication. error code:%s, error message:%s", $ack->getErrorCode(), $ack->getErrorMessage()));
        }
    }

    /**
     * @param int $clientId
     * @param string $destination
     * @param string $filter
     * @throws \Exception
     */
    public function subscribe($clientId=1001, $destination = "example", $filter = ".*\\..*")
    {
        $this->clientId = $clientId;
        $this->destination = $destination;

        $this->rollback(0);

        $sub = new Sub();
        $sub->setDestination($this->destination);
        $sub->setClientId($this->clientId);
        $sub->setFilter($filter);

        $packet = new Packet();
        $packet->setType(PacketType::SUBSCRIPTION);
        $packet->setBody($sub->serializeToString());
        $this->writeWithHeader($packet->serializeToString());

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);

        if ($packet->getType() != PacketType::ACK) {
            throw new \Exception("Subscribe error.");
        }

        $ack = new Ack();
        $ack->mergeFromString($packet->getBody());
        if ($ack->getErrorCode() > 0) {
            throw new \Exception(sprintf("Failed to subscribe. error code:%s, error message:%s", $ack->getErrorCode(), $ack->getErrorMessage()));
        }
    }

    public function unSubscribe()
    {
        // TODO: Implement unSubscribe() method.
    }

    /**
     * @param int $size
     * @return Message
     * @throws \Exception
     */
    public function get($size=100)
    {
        $message = $this->getWithoutAck($size);
        $this->ack($message->getId());
        return $message;
    }

    /**
     * @param int $batchSize
     * @param int $timeout
     * @param int $unit
     * @return Message
     * @throws \Exception
     */
    public function getWithoutAck($batchSize=10, $timeout=-1, $unit=-1)
    {
        $get = new Get();
        $get->setClientId($this->clientId);
        $get->setDestination($this->destination);
        $get->setAutoAck(false);
        $get->setFetchSize($batchSize);
        $get->setTimeout($timeout);
        $get->setUnit($unit);

        $packet = new Packet();
        $packet->setType(PacketType::GET);
        $packet->setBody($get->serializeToString());

        $this->writeWithHeader($packet->serializeToString());

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);

        $message = new Message();

        switch ($packet->getType()) {
            case PacketType::MESSAGES:
                $messages = new Messages();
                $messages->mergeFromString($packet->getBody());

                if ($messages->getBatchId() > 0) {
                    $message->setId($messages->getBatchId());

                    foreach ($messages->getMessages()->getIterator() as $v) {
                        $entry = new Entry();
                        $entry->mergeFromString($v);
                        $message->addEntries($entry);
                    }
                }

                break;
            case PacketType::ACK:
                $ack = new Ack();
                $ack->mergeFromString($packet->getBody());
                if ($ack->getErrorCode() > 0) {
                    throw new \Exception(sprintf("get data error. error code:%s, error message:%s", $ack->getErrorCode(), $ack->getErrorMessage()));
                }
                break;
            default:
                throw new \Exception(sprintf("unexpected packet type:%s", $packet->getType()));
                break;
        }

        return $message;
    }

    public function ack($messageId=0)
    {
        if ($messageId) {
            $clientAck = new ClientAck();
            $clientAck->setDestination($this->destination);
            $clientAck->setClientId($this->clientId);
            $clientAck->setBatchId($messageId);

            $packet = new Packet();
            $packet->setType(PacketType::CLIENTACK);
            $packet->setBody($clientAck->serializeToString());

            $this->writeWithHeader($packet->serializeToString());
        }
    }

    public function rollback($batchId=0)
    {
        $cb = new ClientRollback();
        $cb->setBatchId($batchId);
        $cb->setClientId($this->clientId);
        $cb->setDestination($this->destination);

        $packet = new Packet();
        $packet->setType(PacketType::CLIENTROLLBACK);
        $packet->setBody($cb->serializeToString());

        $this->writeWithHeader($packet->serializeToString());
    }

    abstract protected function doConnect($host="127.0.0.1", $port=11111, $connectionTimeout=10, $readTimeout = 30, $writeTimeout = 30);

    abstract protected function readNextPacket();

    abstract protected function writeWithHeader($data);
}