<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;
use Fernando\PuskerDB\Exception\PuskerException;

final class DropRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): array
    {
        switch ($this->ast['drop_type']) {
            case 'DATABASE':
                return $this->dropDatabase();
            case 'TABLE':
                return $this->dropTable();
            default:
                return [];
        }
    }

    public function dropDatabase(): array
    {
        $database = $this->ast['alvo']['value'];

        if (!$this->storage->isdir($database)) {
            PuskerException::throw("No databases were selected.", []);
            return [];
        }

        if (!$this->storage->unsetDatabase($database)) {
            PuskerException::throw("Error deleting database '{$database}'.", []);
            return [];
        } else {
            if ($this->runtime->database === $database) {
                $this->runtime->input = '[puskerdb]> ';
                $this->runtime->database = '';
            }
        }
        return [];
    }

    public function dropTable(): array
    {
        $table = $this->ast['alvo']['value'];

        if (!$this->storage->get("{$this->runtime->database}/{$table}.json")) {
            PuskerException::throw("Table don't exists '{$table}' in database '{$this->runtime->database}'.", []);
            return [];
        }

        if ($this->storage->unsetFile($this->runtime->database, $table)) {
            return [];
        } else {
            PuskerException::throw("Error deleting table '{$table}' in database '{$this->runtime->database}'.", []);
            return [];
        }
    }
}
