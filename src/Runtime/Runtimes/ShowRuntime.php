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

    public function runRuntime(): void
    {
        switch ($this->ast['show_type']) {
            case 'DATABASE':
                $this->showDatabase();
                break;
            case 'DATABASES':
                $this->showDatabases();
                break;
            case 'TABLES':
                $this->showTables();
                break;
        }
    }

    public function showDatabases(): void
    {
        $databases = $this->storage->getAllFolders();

        if (!$databases) {
            echo "No databases to be shown" . PHP_EOL;
            return;
        }

        $consoleTable = new ConsoleTableUtils();
        $consoleTable->setHeaders(['Databases']);

        foreach ($databases as $_ => $database) {
            $consoleTable->addRow([$database]);
        }

        $consoleTable->render();
    }

    public function showTables(): void
    {
        $tables = $this->storage->getAllFiles($this->ast['database']['value']);

        if (!$tables) {
            echo "No tables to be shown" . PHP_EOL;
            return;
        }

        $consoleTable = new ConsoleTableUtils();
        $consoleTable->setHeaders(['Tables']);

        foreach ($tables as $_ => $table) {
            $consoleTable->addRow([explode('.', $table)[0]]);
        }

        $consoleTable->render();
    }

    public function showDatabase(): void
    {
        echo "Your database is '{$this->runtime->database}'" . PHP_EOL;
    }
}
