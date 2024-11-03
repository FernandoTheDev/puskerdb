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

        // Remove as chaves primárias e auto-incremento do total de colunas
        if (array_key_exists('pkey', $columnsDb)) {
            $sub--;
            unset($columnsDb['pkey']);
        }

        if (array_key_exists('auto_increment', $columnsDb)) {
            $sub -= 2;
            unset($columnsDb['auto_increment_index']);
            unset($columnsDb['auto_increment']);
        }

        $diff = count($columns) - count($columnsDb) + $sub;

        // Lógica para suportar o asterisco (*)
        if (count($columns) === 1 && $columns[0] === '*') {
            $columns = array_keys($columnsDb); // Seleciona todas as colunas
        } else {
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

            // Preparar o mapeamento de colunas para facilitar a filtragem
            $afterColumns = $columns;
            $columns = [];
            foreach ($afterColumns as $index => $column) {
                $columns[$column] = $index;
            }

            foreach ($columnsDb as $column => $value) {
                if (!array_key_exists($column, $columns)) {
                    unset($columnsDb[$column]);
                }
            }
        }

        // Aplicar condições, se existirem
        if ($this->ast['conditions'] != null) {
            $data = $this->executeConditions($this->ast['conditions'], $fileData['data']);
        } else {
            $data = $fileData['data'];
        }

        $consoleTable = new ConsoleTableUtils();
        $consoleTable->setHeaders(array_keys($columnsDb));

        foreach ($data as $row => $values) {
            $filteredRow = [];
            foreach ($values as $index => $_data) {
                if ($index === -1) {
                    $filteredRow[] = 'N/A';
                } else if (in_array($index, $columns, true)) {
                    $filteredRow[] = $_data ?? 'N/A';
                }
            }
            $consoleTable->addRow($filteredRow);
        }

        $consoleTable->render();
    }
}
