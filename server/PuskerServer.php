<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\ConnectionInterface;
use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Pusker\Pusker;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Ratchet\MessageComponentInterface;

class WebSocketServer implements MessageComponentInterface
{
    const MESSAGE_WELCOME = 'Bem vindo ao PuskerDB. 
O id da sua conexão é %s
Versão do servidor: 0.0.1 PuskerDB (PDB Server)';
    protected $clients;

    public function __construct(private Runtime $runtime)
    {
        $this->runtime->setCli(true);
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $conn->send(sprintf(self::MESSAGE_WELCOME, $conn->resourceId));
        echo "Novo cliente conectado: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $lexer = new Lexer($msg);
        $parser = new Parser($lexer->getTokens());
        $from->send($this->runtime->run($parser->parse()));
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "Cliente desconectado: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Erro: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = new Ratchet\App('127.0.0.1', 7520);
$server->route('/', new WebSocketServer(new Runtime(new Storage(), new Pusker())), ['*']);
$server->run();
