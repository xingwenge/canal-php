<?php
namespace sample;

use client\SimpleCanalConnector;
use Com\Alibaba\Otter\Canal\Protocol\Column;
use Com\Alibaba\Otter\Canal\Protocol\Entry;
use Com\Alibaba\Otter\Canal\Protocol\EntryType;
use Com\Alibaba\Otter\Canal\Protocol\EventType;
use Com\Alibaba\Otter\Canal\Protocol\RowChange;
use Com\Alibaba\Otter\Canal\Protocol\RowData;

require_once __DIR__. '/../init.php';

try {
    $conn = new SimpleCanalConnector();
    $conn->connect('127.0.0.1', 11111, true);
    $conn->checkValid();
    $conn->subscribe(".*\\..*");

    while (true) {
        $message = $conn->get(100);
        $entries = $message->getEntries();
        if ($entries) {
            foreach ($entries as $entry) {
                pt($entry);
            }
        }
        sleep(1);
    }

    $conn->disConnect();
} catch (\Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}

/**
 * @param Entry $entry
 * @throws \Exception
 */
function pt($entry)
{
    switch ($entry->getEntryType()) {
        case EntryType::TRANSACTIONBEGIN:
        case EntryType::TRANSACTIONEND:
            return;
            break;
    }

    $rowChange = new RowChange();
    $rowChange->mergeFromString($entry->getStoreValue());
    $evenType = $rowChange->getEventType();
    $header = $entry->getHeader();

    echo sprintf("================> binlog[%s : %d],name[%s,%s], eventType: %s", $header->getLogfileName(), $header->getLogfileOffset(), $header->getSchemaName(), $header->getTableName(), $header->getEventType()), PHP_EOL;
    echo $rowChange->getSql(), PHP_EOL;

    /** @var RowData $rowData */
    foreach ($rowChange->getRowDatas() as $rowData) {
        switch ($evenType) {
            case EventType::DELETE:
                ptColumn($rowData->getBeforeColumns());
                break;
            case EventType::INSERT:
                ptColumn($rowData->getAfterColumns());
                break;
            default:
                echo '-------> before', PHP_EOL;
                ptColumn($rowData->getBeforeColumns());
                echo '-------> after', PHP_EOL;
                ptColumn($rowData->getAfterColumns());
                break;
        }
    }
}

function ptColumn($columns) {
    /** @var Column $column */
    foreach ($columns as $column) {
        echo sprintf("%s : %s  update= %s", $column->getName(), $column->getValue(), var_export($column->getUpdated(), true)), PHP_EOL;
    }
}