<?php
namespace client;

use Com\Alibaba\Otter\Canal\Protocol\Ack;
use Com\Alibaba\Otter\Canal\Protocol\ClientAck;
use Com\Alibaba\Otter\Canal\Protocol\ClientAuth;
use Com\Alibaba\Otter\Canal\Protocol\ClientRollback;
use Com\Alibaba\Otter\Canal\Protocol\Entry;
use Com\Alibaba\Otter\Canal\Protocol\Get;
use Com\Alibaba\Otter\Canal\Protocol\Messages;
use Com\Alibaba\Otter\Canal\Protocol\Packet;
use Com\Alibaba\Otter\Canal\Protocol\PacketType;
use Com\Alibaba\Otter\Canal\Protocol\Sub;

class SimpleCanalConnector implements CanalConnector
{
    /** @var TcpClient */
    protected $socket;
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
     * @param $host
     * @param $port
     * @param int $connectionTimeout
     *  Timeout in seconds
     * @param int $readTimeout
     *  Timeout in seconds
     * @param int $writeTimeout
     *  Timeout in seconds
     * @throws \Exception
     */
    public function connect($host = 'localhost', $port = 9090, $connectionTimeout=10, $readTimeout = 3600, $writeTimeout = 3600)
    {
        $this->readTimeout = $readTimeout;
        $this->writeTimeout = $writeTimeout;

        $this->socket = new TcpClient($host, $port, true);
        $this->socket->setConnectTimeout($connectionTimeout);
        $this->socket->setRecvTimeout($this->readTimeout);
        $this->socket->setSendTimeout($this->writeTimeout);
        $this->socket->open();

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);

        if ($packet->getType() != PacketType::HANDSHAKE) {
            throw new \Exception("conn error.");
        }
    }

    /**
     * @throws \Exception
     */
    public function disConnect()
    {
        if ($this->socket) {
            $this->rollback(0);
            $this->socket->close();
        }
    }

    /**
     * @param $username
     * @param $password
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
     * @return string
     * @throws \Exception
     */
    private function readNextPacket()
    {
        $data = $this->socket->read($this->packetLen);
        return $this->socket->read(unpack("N", $data)[1]);
    }

    /**
     * @param $data
     * @throws \Exception
     */
    private function writeWithHeader($data)
    {
        $this->socket->write(pack("N", strlen($data)));
        $this->socket->write($data);
    }

    /**
     * @param string $destination
     * @param string $filter
     * @throws \Exception
     */
    public function subscribe($destination = "example", $filter = ".*\\..*")
    {
        $this->clientId = 1001;
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
     *  batch size.
     * @return Message|mixed
     * @throws \Exception
     */
    public function get($size=10)
    {
        $message = $this->getWithoutAck($size);
        $this->ack($message->getId());
        return $message;
    }

    /**
     * 允许指定batchSize，一次可以获取多条，每次返回的对象为Message
     *
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

    /**
     * @param int $messageId
     * @throws \Exception
     */
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

    /**
     * @param int $batchId
     * @throws \Exception
     */
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
}
