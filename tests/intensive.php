<?php
use Fernando\PuskerDB\Pusker\Pusker;

require_once __DIR__ . '/../vendor/autoload.php';

$pusker = new Pusker(database: 'project');

$batchSize = 1000;
$totalRecords = 10000;
$querys = [];

for ($i = 0; $i < $totalRecords; $i++) {
    $user_id = $i + 1000000;
    $payment_id = $i;

    $querys[] = "INSERT INTO payments (payment_id, user_id) VALUES ({$payment_id}, {$user_id})";

    if (count($querys) >= $batchSize || $i == $totalRecords - 1) {
        $pusker->query(implode(';', $querys))
            ->execute();

        $querys = [];
    }
}
