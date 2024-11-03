<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;
use Fernando\PuskerDB\Exception\PuskerException;

final class SelectRuntime extends Runtime
{
    private array $columnsCache = [];

    public function __construct(
        private readonly Runtime $runtime,
        private readonly array $ast,
        protected readonly Storage $storage
    ) {
    }

    public function runRuntime(): array
    {
        $database = $this->runtime->database;
        if (!$this->storage->isdir($database)) {
            PuskerException::throw("No databases were selected.", []);
            return [];
        }

        $table = $this->ast['table']['value'];
        $fileData = $this->storage->get("{$database}/{$table}.json");
        if (!$fileData) {
            PuskerException::throw("Table doesn't exist.", []);
            return [];
        }

        $requestedColumns = $this->ast['columns'];
        $tableColumns = $this->prepareTableColumns($fileData['columns']);

        // Validate and prepare columns
        $columns = $this->validateAndPrepareColumns($requestedColumns, $tableColumns);
        if (empty($columns)) {
            PuskerException::throw("No columns were selected.", []);
            return [];
        }

        // Get filtered data
        $data = $this->filterData($fileData['data'], $columns);

        return $this->formatOutput($data, $columns);
    }

    private function prepareTableColumns(array $columns): array
    {
        // Remove system columns
        unset($columns['pkey'], $columns['auto_increment'], $columns['auto_increment_index']);
        $this->columnsCache = $columns;
        return $columns;
    }

    private function validateAndPrepareColumns(array $requestedColumns, array $tableColumns): array
    {
        // Handle wildcard
        if (count($requestedColumns) === 1 && $requestedColumns[0] === '*') {
            // print_r(array_keys($tableColumns));
            // die('1');
            return array_keys($tableColumns);
        }

        // Validate column count
        if (count($requestedColumns) > count($tableColumns)) {
            PuskerException::throw("No columns were selected.", []);
            return [];
        }

        // Validate column existence
        foreach ($requestedColumns as $column) {
            if (!isset($tableColumns[$column])) {
                PuskerException::throw("No columns were selected.", []);
                return [];
            }
        }

        return array_flip($requestedColumns);
    }

    private function filterData(array $data, array $columns): array
    {
        if ($this->ast['conditions'] !== null) {
            return $this->executeConditions($this->ast['conditions'], $data);
        }
        return $data;
    }

    private function formatOutput(array $data, array $columns): array
    {
        $output = [];
        foreach ($data as $rowIndex => $row) {
            $formattedRow = [];
            foreach ($row as $colIndex => $value) {
                if ($colIndex === -1) {
                    $formattedRow[] = 'N/A';
                    continue;
                }

                if (in_array($colIndex, $columns)) {
                    $formattedRow[$colIndex] = $value ?? 'N/A';
                }
            }
            if (!empty($formattedRow)) {
                $output[$rowIndex] = $formattedRow;
            }
        }
        return ['type' => 'table', 'header' => $this->columnsCache, 'data' => $output];
    }
}
