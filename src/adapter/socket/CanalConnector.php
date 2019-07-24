<?php
namespace xingwenge\canal_php\adapter\socket;

use xingwenge\canal_php\adapter\CanalConnectorBase;

class CanalConnector extends CanalConnectorBase
{
    /** @var TcpClient */
    protected $client;

    /**
     * @param string $host
     * @param int $port
     * @param int $connectionTimeout
     * @param int $readTimeout
     * @param int $writeTimeout
     * @throws \Exception
     */
    protected function doConnect( $host = "127.0.0.1", $port = 11111, $connectionTimeout = 10, $readTimeout = 30, $writeTimeout = 30 )
    {
        $this->client = new TcpClient($host, $port, true);
        $this->client->setConnectTimeout($connectionTimeout);
        $this->client->setRecvTimeout($readTimeout);
        $this->client->setSendTimeout($writeTimeout);
        $this->client->open();
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function readNextPacket()
    {
        $data = $this->client->read($this->packetLen);
        $dataLen = unpack("N", $data)[1];
        return $this->client->read($dataLen);
    }

    /**
     * @param $data
     * @throws \Exception
     */
    protected function writeWithHeader( $data )
    {
        $this->client->write(pack("N", strlen($data)));
        $this->client->write($data);
    }
}
