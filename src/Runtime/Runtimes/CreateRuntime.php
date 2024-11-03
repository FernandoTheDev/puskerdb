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

    public function runRuntime(): array
    {
        if ($this->ast['redirect'] === 'DATABASE') {
            return $this->createDatabase();
        }
        return $this->createTable();
    }

    private function createDatabase(): array
    {
        $database = $this->ast['database']['value'];

        if ($this->storage->isdir($database)) {
            return [];
        }

        if ($this->storage->makedir($database)) {
            return [];
        }
        return [];
    }

    private function createTable(): array
    {
        $database = $this->runtime->database;

        if ($database === '') {
            PuskerException::throw(
                "No databases were selected.",
                []
            );
            return [];
        }

        if (!$this->storage->isdir($database)) {
            return [];
        }

        $table = $this->ast['table']['value'];
        if ($this->storage->get("{$database}/{$table}.json") != false) {
            return [];
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
                            PuskerException::throw(
                                "PKEY already in use!.",
                                []
                            );
                            return [];
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
                            PuskerException::throw(
                                "AUTO_INCREMENTEY already in use!.",
                                []
                            );
                            return [];
                        }
                    }
                }
            }

            if (array_key_exists($values['name'], $data['columns'])) {
                PuskerException::throw(
                    "{$values['name']} already in use!.",
                    []
                );
                return [];
            }
            $data['columns'][$values['name']] = $values['type'];
        }

        if ($this->storage->table($database, $table, $data)) {
            return [];
        }
        return [];
    }
}
