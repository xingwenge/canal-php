<?php
namespace sample;

use xingwenge\canal\php\Fmt;
use xingwenge\canal\php\socket\CanalConnector;

require_once __DIR__. '/../../vendor/autoload.php';

try {
    $conn = new CanalConnector();
    $conn->connect("127.0.0.1", 11111, 10, 1800, 1800);
    $conn->checkValid();
    $conn->subscribe("example", ".*\\..*");

    while (true) {
        $message = $conn->get(10);
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