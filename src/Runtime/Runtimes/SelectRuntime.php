<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;

final class SelectRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected readonly Storage $storage)
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
        $sub = 0;
        // $values = $this->ast['values'];

        if (array_key_exists('pkey', $columnsDb)) {
            $sub--;
            // $diff++;
            unset($columnsDb['pkey']);
        }

        // var_dump($columnsDb);

        if (array_key_exists('auto_increment', $columnsDb)) {
            $sub -= 2;
            // $diff += 2;
        }

        $diff = count($columns) - count($columnsDb) + $sub;
        // var_dump($diff);

        if ($diff > 1) {
            echo "You passed " . count($columns) . " columns but the table requires " . (count($columnsDb)) . PHP_EOL;
            return;
        }

        if ((count($columns) + $sub) > count($columnsDb)) {
            echo "You declared " . count($columns) . " columns and " . (count($columnsDb) - $sub) . " values." . PHP_EOL;
            return;
        }

        foreach ($columns as $column) {
            if (!array_key_exists($column, $columnsDb)) {
                echo "Column {$column} doesn't exist." . PHP_EOL;
                return;
            }
        }

        // $columnsDb = array_keys($fileData['columns']);

        // var_dump($columns);
        $afterColumns = $columns;
        $columns = [];

        foreach ($afterColumns as $index => $column) {
            $columns[$column] = $index;
        }

        // var_dump($columns);

        foreach ($columnsDb as $column => $value) {
            // var_dump($column);
            if (!array_key_exists($column, $columns)) {
                unset($columnsDb[$column]);
            }
        }

        if ($this->ast['conditions'] != null) {
            $data = $this->executeConditions($this->ast['conditions'], $fileData['data']);
        } else {
            $data = $fileData['data'];
        }

        $consoleTable = new ConsoleTableUtils();
        $consoleTable->setHeaders(array_keys($columnsDb));

        // var_dump($fileData['data']);

        foreach ($data as $row => $values) {
            $filteredRow = [];
            $i = 0;
            foreach ($values as $index => $_data) {
                // var_dump($index);
                if ($index === -1) {
                    $filteredRow[] = 'N/A';
                } else if (array_key_exists($index, $columns)) {
                    $filteredRow[] = $_data ?? 'N/A';
                }
                $i++;
            }
            $consoleTable->addRow($filteredRow);
            // $consoleTable->addRow([explode('.', $table)[0]]);
        }

        $consoleTable->render();
    }
}
