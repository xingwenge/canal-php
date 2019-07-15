<?php
namespace client;

use Com\Alibaba\Otter\Canal\Protocol\Ack;
use Com\Alibaba\Otter\Canal\Protocol\ClientAck;
use Com\Alibaba\Otter\Canal\Protocol\ClientAuth;
use Com\Alibaba\Otter\Canal\Protocol\Entry;
use Com\Alibaba\Otter\Canal\Protocol\Get;
use Com\Alibaba\Otter\Canal\Protocol\Messages;
use Com\Alibaba\Otter\Canal\Protocol\Packet;
use Com\Alibaba\Otter\Canal\Protocol\PacketType;
use Com\Alibaba\Otter\Canal\Protocol\Sub;
use function sample\ptColumn;

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
     * @param $host
     * @param $port
     * @param $persist
     * @param int $connectionTimeout
     *  Timeout in seconds
     * @param int $readTimeout
     *  Timeout in seconds
     * @param int $writeTimeout
     *  Timeout in seconds
     * @throws \Exception
     */
    public function connect($host = 'localhost', $port = 9090, $persist = false, $connectionTimeout=10, $readTimeout = 3600, $writeTimeout = 3600)
    {
        $this->readTimeout = $readTimeout;
        $this->writeTimeout = $writeTimeout;

        $this->socket = new TcpClient($host, $port, $persist);
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

    public function disConnect()
    {
        $this->socket->close();
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
        $data = $packet->serializeToString();
        $this->writeWithHeader($data);

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data);
        if ($packet->getType() != PacketType::ACK) {
            throw new \Exception("Auth error.");
        }
        $ack = new Ack();
        $ack->mergeFromString($packet->getBody());
        if ($ack->getErrorCode() > 0) {
            throw new \Exception(sprintf("Auth error. error code:%s, error message:%s", $ack->getErrorCode(), $ack->getErrorMessage()));
        }
    }

    private function readNextPacket()
    {
        $data = $this->socket->read($this->packetLen);
        return $this->socket->read(unpack("N", $data)[1]);
    }

    private function writeWithHeader($data)
    {
        $this->socket->write(pack("N", strlen($data)));
        $this->socket->write($data);
    }

    /**
     * @param string $clientId
     * @param string $destination
     * @param string $filter
     * @throws \Exception
     */
    public function subscribe($clientId = "1003", $destination = "example", $filter = ".*\\..*")
    {
        $this->clientId = $clientId;
        $this->destination = $destination;

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
            throw new \Exception(sprintf("Subscribe error. error code:%s, error message:%s", $ack->getErrorCode(), $ack->getErrorMessage()));
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
    public function get($size=100)
    {
        $message = $this->getWithoutAck($size);
//        $this->ack($message->getId());
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
    public function getWithoutAck($batchSize=1000, $timeout=-1, $unit=-1)
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

    public function rollback()
    {
        // TODO: Implement rollback() method.
    }
}
