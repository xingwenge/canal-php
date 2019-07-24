<?php
namespace xingwenge\canal_php;

use xingwenge\canal_php\adapter\CanalConnectorBase;

class CanalConnectorFactory
{
    const CLIENT_SOCKET = 1;
    const CLIENT_SWOOLE = 2;

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
            case self::CLIENT_SOCKET:
                return new \xingwenge\canal_php\adapter\socket\CanalConnector();
            case self::CLIENT_SWOOLE:
                return new \xingwenge\canal_php\adapter\swoole\CanalConnector();
            default:
                throw new \Exception("Unknown client type");
        }
    }
}