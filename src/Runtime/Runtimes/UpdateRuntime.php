<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Exception\PuskerException;

final class UpdateRuntime extends Runtime
{
    public function __construct(
        private readonly Runtime $runtime,
        private readonly array $ast,
        protected Storage $storage
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

        foreach ($this->ast['updates'] as $update) {
            $column = $update['column'];
            if (
                !isset($fileData['columns'][$column]) ||
                in_array($column, ['auto_increment', 'auto_increment_index', 'pkey'])
            ) {
                PuskerException::throw("Invalid column {$column}.", []);
                return [];
            }

            $expectedType = $fileData['columns'][$column];
            $value = $update['value'];
            if ($expectedType === 'NUMBER' && $value['type'] !== 'NUMBER') {
                PuskerException::throw("Type mismatch for column {$column}. Expected NUMBER.", []);
                return [];
            }

            if (array_key_exists('auto_increment', $fileData['columns'])) {
                if ($fileData['columns']['auto_increment'] === $column) {
                    PuskerException::throw("Cannot update auto_increment column.", []);
                    return [];
                }
            }
        }

        $matchingRows = isset($this->ast['conditions']) && !empty($this->ast['conditions'])
            ? $this->executeConditions($this->ast['conditions'], $fileData['data'])
            : $fileData['data'];

        if (empty($matchingRows)) {
            return [];
        }

        $updatedCount = 0;
        foreach ($fileData['data'] as $id => $row) {
            if (!isset($this->ast['conditions']) || empty($this->ast['conditions']) || in_array($row, $matchingRows)) {
                $updatedRow = $row;

                foreach ($this->ast['updates'] as $update) {
                    $column = $update['column'];
                    $value = $update['value'];

                    if ($fileData['columns'][$column] === 'NUMBER') {
                        $updatedRow[$column] = (int) $value['value'];
                    } else {
                        $updatedRow[$column] = trim($value['value'], '"\'');
                    }
                }
                $fileData['data'][$id] = $updatedRow;
                $updatedCount++;
            }
        }

        $this->storage->put("{$database}/{$table}.json", $fileData);
        return ['type' => 'rows_affected', 'rows' => $updatedCount];
    }
}
