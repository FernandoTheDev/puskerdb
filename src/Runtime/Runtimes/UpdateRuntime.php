<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;

final class UpdateRuntime extends Runtime
{
    public function __construct(
        private readonly Runtime $runtime,
        private readonly array $ast,
        protected Storage $storage
    ) {
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

        // Validar as colunas a serem atualizadas
        foreach ($this->ast['updates'] as $update) {
            $column = $update['column'];
            // Verificar se a coluna existe
            if (
                !isset($fileData['columns'][$column]) ||
                in_array($column, ['auto_increment', 'auto_increment_index', 'pkey'])
            ) {
                echo "Invalid column {$column}." . PHP_EOL;
                return;
            }

            // Verificar o tipo da coluna
            $expectedType = $fileData['columns'][$column];
            $value = $update['value'];
            if ($expectedType === 'NUMBER' && $value['type'] !== 'NUMBER') {
                echo "Type mismatch for column {$column}. Expected NUMBER." . PHP_EOL;
                return;
            }

            // Verificar se está tentando atualizar uma coluna auto_increment
            if (array_key_exists('auto_increment', $fileData['columns'])) {
                if ($fileData['columns']['auto_increment'] === $column) {
                    echo "Cannot update auto_increment column." . PHP_EOL;
                    return;
                }
            }
        }

        // Se não houver condições, atualizar todos os registros
        $matchingRows = isset($this->ast['conditions']) && !empty($this->ast['conditions'])
            ? $this->executeConditions($this->ast['conditions'], $fileData['data'])
            : $fileData['data'];

        if (empty($matchingRows)) {
            echo "No rows to update." . PHP_EOL;
            return;
        }

        // Atualizar os registros
        $updatedCount = 0;
        foreach ($fileData['data'] as $id => $row) {
            if (!isset($this->ast['conditions']) || empty($this->ast['conditions']) || in_array($row, $matchingRows)) {
                $updatedRow = $row;
                // Aplicar as atualizações
                foreach ($this->ast['updates'] as $update) {
                    $column = $update['column'];
                    $value = $update['value'];
                    // Processar o valor baseado no tipo
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

        // Salvar as alterações
        $this->storage->put("{$database}/{$table}.json", $fileData);
        echo "{$updatedCount} row(s) updated successfully." . PHP_EOL;
    }
}
