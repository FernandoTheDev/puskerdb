<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Exception\PuskerException;

final class CreateRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): void
    {
        if ($this->ast['redirect'] === 'DATABASE') {
            $this->createDatabase();
            return;
        }
        $this->createTable();
    }

    private function createDatabase(): void
    {
        $database = $this->ast['database']['value'];

        if ($this->storage->isdir($database)) {
            echo "Database already exists '{$database}'." . PHP_EOL;
            return;
        }

        if ($this->storage->makedir($database)) {
            echo "Database created." . PHP_EOL;
            return;
        } else {
            echo "Failed to create database '{$database}'." . PHP_EOL;
        }
    }

    private function createTable(): void
    {
        $database = $this->runtime->database;

        if ($database === '') {
            PuskerException::expect("No databases were selected.");
            return;
        }

        if (!$this->storage->isdir($database)) {
            echo "Database don't exists '{$database}'." . PHP_EOL;
            return;
        }

        $table = $this->ast['table']['value'];
        if ($this->storage->get("{$database}/{$table}.json") != false) {
            echo "Table already exists '{$table}'." . PHP_EOL;
            return;
        }

        $primaryKey = false;
        $autoIncrement = false;
        $data = [
            "columns" => [],
            "data" => []
        ];

        foreach ($this->ast['columns'] as $column => $values) {
            if (count($values['constraints']) > 0) {
                foreach ($values['constraints'] as $constraint => $value) {
                    if ($value === 'PKEY') {
                        if ($primaryKey === false) {
                            $data['columns']['pkey'] = $values['name'];
                            $primaryKey = true;
                        } else {
                            PuskerException::expect("PKEY already in use!");
                            return;
                        }
                        continue;
                    }

                    if ($value === 'AUTO_INCREMENT') {
                        if ($autoIncrement === false) {
                            $data['columns']['auto_increment'] = $values['name'];
                            $data['columns']['auto_increment_index'] = 0;
                            $autoIncrement = true;
                            continue;
                        } else {
                            PuskerException::expect("AUTO_INCREMENTEY already in use!");
                            return;
                        }
                    }

                    var_dump($primaryKey);
                    var_dump($autoIncrement);
                }
            }

            if (array_key_exists($values['name'], $data['columns'])) {
                PuskerException::expect("{$values['name']} already in use!");
                return;
            }
            $data['columns'][$values['name']] = $values['type'];
        }

        if ($this->storage->table($database, $table, $data)) {
            echo "Table created '{$table}' in database '{$database}'." . PHP_EOL;
            return;
        } else {
            echo "Failed to create table '{$table}' in '{$database}'." . PHP_EOL;
        }
    }
}
