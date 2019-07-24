<?php
namespace xingwenge\canal_php\sample;

use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\Fmt;

require_once __DIR__. '/../../vendor/autoload.php';

ini_set('display_errors', 'On');
error_reporting(E_ALL);

try {
    $client = CanalConnectorFactory::createClient(CanalConnectorFactory::CLIENT_SOCKET);
    # $client = CanalConnectorFactory::createClient(CanalConnectorFactory::CLIENT_SWOOLE);
    $client->connect("127.0.0.1", 11111);
    $client->checkValid();
    $client->subscribe("1001", "example");

    while (true) {
        $message = $client->get(100);
        $entries = $message->getEntries();
        if ($entries) {
            foreach ($entries as $entry) {
                Fmt::println($entry);
            }
        }
        sleep(1);
    }

    $client->disConnect();
} catch (\Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}