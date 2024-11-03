<?php
require __DIR__ . '/../vendor/autoload.php';

use WebSocket\Client;

$host = '127.0.0.1';
$port = 7520;

try {
    // Conectando ao servidor WebSocket
    $client = new Client("ws://$host:$port");

    // Enviando mensagem para o servidor
    $message = "Olá, servidor!";
    $client->send($message);
    echo "Mensagem enviada: $message\n";

    // Lendo a resposta do servidor
    $response = $client->receive();
    echo "Recebido do servidor: $response\n";

    // Fechando a conexão
    $client->close();
} catch (Exception $e) {
    echo "Falha ao conectar: {$e->getMessage()}\n";
}
