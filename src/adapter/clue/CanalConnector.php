<?php
namespace xingwenge\canal_php\adapter\clue;

use Socket\Raw\Factory;
use xingwenge\canal_php\adapter\CanalConnectorBase;

class CanalConnector extends CanalConnectorBase
{
    /** @var \Socket\Raw\Socket */
    protected $client;

    /**
     * @param string $host
     * @param int $port
     * @param int $connectionTimeout
     * @param int $readTimeout
     * @param int $writeTimeout
     * @throws \Exception
     */
    protected function doConnect($host = "127.0.0.1", $port = 11111, $connectionTimeout = 10, $readTimeout = 30, $writeTimeout = 30)
    {
        $factory = new Factory();
        $this->client = $factory->createClient(sprintf("tcp://%s:%s", $host, $port), $connectionTimeout);
    }

    protected function readNextPacket()
    {
        $data = $this->client->read($this->packetLen);
        $dataLen = unpack("N", $data)[1];
        return $this->client->recv($dataLen, MSG_WAITALL);
    }

    protected function writeWithHeader($data)
    {
        $this->client->write(pack("N", strlen($data)));
        $this->client->write($data);
    }
}
