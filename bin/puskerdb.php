<?php

use WebSocket\Client;

require_once __DIR__ . '/../vendor/autoload.php';

$host = '127.0.0.1';
$port = 7520;

try {
    $client = new Client("ws://{$host}:{$port}");
    echo $client->receive() . PHP_EOL;

    $query = '';

    while (true) {
        $input = readline($query === '' ? 'puskerdb> ' : ' > ');

        if ($input == '') {
            continue;
        }

        $query .= "{$input}\n";

        if (trim($input)[-1] == ';') {
            $query = rtrim($query);
            $query = substr($query, 0, -1);

            $client->send($query);
            $response = $client->receive();

            echo $response . PHP_EOL;
            $query = '';
        }
    }
} catch (Exception $e) {
    echo "Falha ao conectar: {$e->getMessage()}\n";
}
