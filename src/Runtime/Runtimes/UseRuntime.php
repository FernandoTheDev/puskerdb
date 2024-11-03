<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Exception\PuskerException;

final class UseRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): array
    {
        $database = $this->ast['database']['value'];
        if (!$this->storage->isdir($database)) {
            PuskerException::throw("Database dont't exists.", []);
            return [];
        }
        $this->runtime->database = $database;
        $this->runtime->input = "[puskerdb/{$this->runtime->database}]> ";
        return [];
    }
}
