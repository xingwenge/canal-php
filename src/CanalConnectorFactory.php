<?php
namespace xingwenge\canal_php;

use xingwenge\canal_php\adapter\CanalConnectorBase;

class CanalConnectorFactory
{
    private function __construct()
    {

    }

    /**
     * @param $clientType
     * @return CanalConnectorBase
     * @throws \Exception
     */
    public static function createClient($clientType)
    {
        switch($clientType){
            case CanalClient::TYPE_SOCKET:
                return new \xingwenge\canal_php\adapter\socket\CanalConnector();
            case CanalClient::TYPE_SWOOLE:
                return new \xingwenge\canal_php\adapter\swoole\CanalConnector();
            case CanalClient::TYPE_SOCKET_CLUE:
                return new \xingwenge\canal_php\adapter\clue\CanalConnector();
            default:
                throw new \Exception("Unknown client type");
        }
    }
}