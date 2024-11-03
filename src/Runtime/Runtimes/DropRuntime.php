<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;

final class DropRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): void
    {
        switch ($this->ast['drop_type']) {
            case 'DATABASE':
                $this->dropDatabase();
                break;
            case 'TABLE':
                $this->dropTable();
                break;
        }
    }

    public function dropDatabase(): void
    {
        $database = $this->ast['alvo']['value'];

        if (!$this->storage->isdir($database)) {
            echo "Database don't exists '{$database}'." . PHP_EOL;
            return;
        }

        if (!$this->storage->unsetDatabase($database)) {
            echo "Error deleting database '{$database}'." . PHP_EOL;
        } else {
            if ($this->runtime->database === $database) {
                $this->runtime->input = '[puskerdb]> ';
                $this->runtime->database = '';
            }
            echo "Database deleted '{$database}'." . PHP_EOL;
        }
        return;
    }

    public function dropTable(): void
    {
        $table = $this->ast['alvo']['value'];

        if (!$this->storage->get("{$this->runtime->database}/{$table}.json")) {
            echo "Table don't exists '{$table}' in database '{$this->runtime->database}'." . PHP_EOL;
            return;
        }

        if ($this->storage->unsetFile($this->runtime->database, $table)) {
            echo "Table deleted '{$table}'." . PHP_EOL;
            return;
        } else {
            echo "Error deleting table '{$table}'." . PHP_EOL;
            return;
        }
    }
}
