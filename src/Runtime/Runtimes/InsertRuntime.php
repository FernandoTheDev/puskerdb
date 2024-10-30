<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;

final class InsertRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): void
    {
        $database = $this->runtime->database;

        if (!$this->storage->isdir($database)) {
            echo "Select a database first." . PHP_EOL;
            return;
        }

        $table = $this->ast['table']['value'];
        $fileData = $this->storage->get("{$database}/{$table}.json");

        if (!$fileData) {
            echo "Table doesn't exist." . PHP_EOL;
            return;
        }

        $columns = $this->ast['columns'];
        $columnsDb = $fileData['columns'];
        $values = $this->ast['values'];

        $diff = count($columns) - count($columnsDb);
        $sub = 0;

        $data = [];
        $primaryKey = false;

        if (array_key_exists('pkey', $columnsDb)) {
            $primaryKey = $columnsDb['pkey'];
            $sub++;
            $diff++;
        }

        $autoIncrement = false;

        if (array_key_exists('auto_increment', $columnsDb)) {
            $autoIncrement = $columnsDb['auto_increment'];
            $sub += 2;
            $diff += 2;
        }

        if ($diff != 0) {
            echo "You passed " . count($columns) . " columns but the table requires " . (count($columnsDb) - $sub) . PHP_EOL;
            return;
        }

        if ((count($columns) + $sub) != count($columnsDb)) {
            echo "You declared " . count($columns) . " columns and " . (count($columnsDb) - $sub) . " values." . PHP_EOL;
            return;
        }

        // print_r($columns);
        // print_r($values);
        // print_r($columnsDb);

        $indexValue = -1;

        foreach ($columns as $key => $columnName) {
            $indexValue++;

            if (!array_key_exists($columnName, $columnsDb)) {
                echo "Column {$columnName} doesn't exist in table {$table}." . PHP_EOL;
                return;
            }

            if ($primaryKey !== false && $columnName == $primaryKey) {
                if (array_key_exists($values[$indexValue]['value'], $fileData['data'])) {
                    echo "Duplicate entry for primary key {$primaryKey}." . PHP_EOL;
                    return;
                }
                $primaryKey = $values[$indexValue]['value'];
            }

            if ($autoIncrement && $columnsDb['auto_increment'] == $columnName) {
                $data[$columnName] = $fileData['columns']['auto_increment_index'] + 1;
                $fileData['columns']['auto_increment_index']++;
                $indexValue--;
                continue;
            }

            $value = match ($values[$indexValue]['type']) {
                'NUMBER' => (int) $values[$indexValue]['value'],
                'STRING' => (string) str_replace(['"', "'"], '', $values[$indexValue]['value']),
                default => null,
            };
            $data[$columnName] = $value;
        }

        $index = $primaryKey === false ? count($fileData['data']) - 1 : $primaryKey;
        $fileData['data'][$index] = $data;
        $this->storage->put("{$database}/{$table}.json", $fileData);

        echo "Data inserted successfully into table {$table}." . PHP_EOL;
    }
}
