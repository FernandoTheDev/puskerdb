<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;

final class ShowRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): array
    {
        switch ($this->ast['show_type']) {
            case 'DATABASE':
                return $this->showDatabase();
            case 'DATABASES':
                return $this->showDatabases();
            case 'TABLES':
                return $this->showTables();
            default:
                return [];
        }
    }

    public function showDatabases(): array
    {
        $databases = $this->storage->getAllFolders();

        if (!$databases) {
            return [];
        }

        return ['type' => 'table', 'header' => ['Databases' => 0], 'data' => $databases];
    }

    public function showTables(): array
    {
        $tables = $this->storage->getAllFiles($this->ast['database']['value']);

        if (!$tables) {
            return [];
        }

        foreach ($tables as $key => $table) {
            $tables[$key] = str_replace('.json', '', $table);
        }

        return ['type' => 'table', 'header' => ['Tables' => 0], 'data' => $tables];
    }

    public function showDatabase(): array
    {
        return ['type' => 'database', 'database' => $this->runtime->database];
    }
}
