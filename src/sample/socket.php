<?php
namespace xingwenge\canal_php\sample;

use xingwenge\canal_php\Fmt;
use xingwenge\canal_php\socket\CanalConnector;

require_once __DIR__. '/../../vendor/autoload.php';

ini_set('display_errors', 'On');
error_reporting(E_ALL);

try {
    $conn = new CanalConnector();
    $conn->connect("127.0.0.1", 11111, 10, 1800, 1800);
    $conn->checkValid();
    $conn->subscribe("example", ".*\\..*");

    while (true) {
        $message = $conn->get(100);
        $entries = $message->getEntries();
        if ($entries) {
            foreach ($entries as $entry) {
                Fmt::println($entry);
            }
        }
        sleep(1);
    }

    $conn->disConnect();
} catch (\Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}