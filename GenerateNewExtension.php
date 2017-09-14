#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';
# extension file
$extension = __DIR__ . '/Src/Util/Data/extension.json';
$getter = new \Pentagonal\WhoIs\Util\DataGetter($extension);
$data = $getter->createNewRecordExtension();
# Check if has been regenerated
if (is_array($data)) {
    echo "Generated : " . count($data);
} else {
    echo "No change for data.";
}