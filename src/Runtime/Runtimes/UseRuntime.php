<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;

final class UseRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): void
    {
        $database = $this->ast['database']['value'];
        if (!$this->storage->isdir($database)) {
            echo "Database dont't exists." . PHP_EOL;
            return;
        }
        $this->runtime->database = $database;
        $this->runtime->input = "[puskerdb/{$this->runtime->database}]> ";
        // echo "Database set '{$this->runtime->database}'." . PHP_EOL;
    }
}
