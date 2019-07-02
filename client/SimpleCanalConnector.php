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

class SimpleCanalConnector implements CanalConnector
{
    /** @var TcpClient */
    protected $socket;
    protected $destination = "example";
    protected $clientId = 1002;

    public function __construct()
    {
    }

    /**
     * @throws \Exception
     */
    public function connect()
    {
        $this->socket = new TcpClient('127.0.0.1', 11111, true);
        $this->socket->setRecvTimeout(3600000);
        $this->socket->open();

        $data = $this->readNextPacket();
        $packet = new Packet();
        $packet->mergeFromString($data); # pack("H*", "1801")

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
        $ca->setNetReadTimeout(3600000);
        $ca->setNetWriteTimeout(3600000);

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
        $data = $this->socket->read(4);
        return $this->socket->read(unpack("N", $data)[1]);
    }

    private function writeWithHeader($data)
    {
        $this->socket->write(pack("N", strlen($data)));
        $this->socket->write($data);
    }

    /**
     * @param string $filter
     * @throws \Exception
     */
    public function subscribe($filter)
    {
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

    public function get($size)
    {
        $message = $this->getWithoutAck($size);
//        $this->ack($message->getId());
        return $message;
    }

    /**
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
    public function ack($messageId)
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
