<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Exception\PuskerException;

final class DeleteRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): array
    {
        $table = $this->ast['table']['value'];

        if (!$this->storage->get("{$this->runtime->database}/{$table}.json")) {
            PuskerException::throw("Table don't exists '{$table}' in database '{$this->runtime->database}'.", []);
            return [];
        }

        $fileData = $this->storage->get("{$this->runtime->database}/{$table}.json");
        $filteredData['data'] = $this->filterData($fileData['data']);

        $remainingData = array_filter($fileData['data'], fn($row) => !in_array($row, $filteredData));
        array_merge($fileData['columns'], $remainingData);

        if ($this->storage->put("{$this->runtime->database}/{$table}.json", ['data' => array_values($remainingData)])) {
            return ['type' => 'rows_affected', 'rows' => count($remainingData)];
        }
        PuskerException::throw("Error updating table '{$table}' in database '{$this->runtime->database}'.", []);
        return [];
    }

    private function filterData(array $data): array
    {
        if (!empty($this->ast['conditions'])) {
            return $this->executeConditions($this->ast['conditions'], $data);
        }
        return $data;
    }
}
