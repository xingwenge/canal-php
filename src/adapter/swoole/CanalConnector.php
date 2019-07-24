<?php
namespace xingwenge\canal_php\adapter\swoole;

use xingwenge\canal_php\adapter\CanalConnectorBase;

/**
 * Class CanalConnector
 * @package xingwenge\canal_php\adapter\swoole
 */
class CanalConnector extends CanalConnectorBase
{
    /** @var \swoole_client */
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
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$this->client->connect($host, $port, $connectionTimeout)) {
            throw new \Exception("connect failed. Error: {$this->client->errCode}");
        }
    }

    protected function readNextPacket()
    {
        $data = $this->client->recv($this->packetLen);
        $dataLen = unpack("N", $data)[1];
        return $this->client->recv($dataLen, true);
    }

    protected function writeWithHeader($data)
    {
        $this->client->send(pack("N", strlen($data)));
        $this->client->send($data);
    }
}
