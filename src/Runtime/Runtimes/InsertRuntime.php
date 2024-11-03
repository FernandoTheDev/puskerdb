<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;

final class InsertRuntime extends Runtime
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

        $columns = $this->ast['columns'];
        $columnsDb = $fileData['columns'];
        $values = $this->ast['values'];

        // Verificar auto_increment
        $hasAutoIncrement = isset($columnsDb['auto_increment']);
        $autoIncrementColumn = $hasAutoIncrement ? $columnsDb['auto_increment'] : null;
        $currentAutoIncrementIndex = $hasAutoIncrement ? $columnsDb['auto_increment_index'] : 0;

        // Preparar dados
        $data = [];

        // Identificar colunas regulares (excluindo metadados)
        $regularColumns = array_filter(
            $columnsDb,
            fn($key) =>
            !in_array($key, ['auto_increment', 'auto_increment_index', 'pkey']),
            ARRAY_FILTER_USE_KEY
        );

        // Verificar se todas as colunas não-auto_increment foram fornecidas
        $requiredColumns = array_filter(
            array_keys($regularColumns),
            fn($col) => $col !== $autoIncrementColumn
        );

        // Verificar colunas obrigatórias
        foreach ($requiredColumns as $requiredColumn) {
            if (!in_array($requiredColumn, $columns)) {
                echo "Missing required column: {$requiredColumn}" . PHP_EOL;
                return;
            }
        }

        // Verificar se o número de valores corresponde ao número de colunas fornecidas
        if (count($values) !== count($columns)) {
            echo "Number of values doesn't match number of columns." . PHP_EOL;
            return;
        }

        // Se tiver auto_increment e não foi fornecido, gerar próximo valor
        $nextId = $currentAutoIncrementIndex + 1;
        if ($hasAutoIncrement && !in_array($autoIncrementColumn, $columns)) {
            $data[$autoIncrementColumn] = $nextId;
            $fileData['columns']['auto_increment_index'] = $nextId;
        }

        // Processar valores fornecidos
        foreach ($columns as $index => $columnName) {
            if (!isset($values[$index])) {
                echo "Missing value for column {$columnName}." . PHP_EOL;
                return;
            }

            // Verificar se a coluna existe
            if (
                !isset($columnsDb[$columnName]) ||
                in_array($columnName, ['auto_increment', 'auto_increment_index', 'pkey'])
            ) {
                echo "Invalid column {$columnName}." . PHP_EOL;
                return;
            }

            $value = $values[$index];
            $expectedType = $columnsDb[$columnName];

            // Se for coluna auto_increment
            if ($hasAutoIncrement && $columnName === $autoIncrementColumn) {
                if ($value['type'] !== 'NUMBER') {
                    echo "Type mismatch for auto_increment column. Expected NUMBER." . PHP_EOL;
                    return;
                }
                if (isset($fileData['data'][$value['value']])) {
                    echo "Duplicate entry for key 'id'." . PHP_EOL;
                    return;
                }
                $data[$columnName] = (int) $value['value'];
                $nextId = $data[$columnName];
                continue;
            }

            // Processar valor baseado no tipo esperado
            if ($expectedType === 'NUMBER') {
                if ($value['type'] !== 'NUMBER') {
                    echo "Type mismatch for column {$columnName}. Expected NUMBER." . PHP_EOL;
                    return;
                }
                $data[$columnName] = (int) $value['value'];
            } else if ($expectedType === 'STRING') {
                $data[$columnName] = (string) str_replace(['"', "'"], '', $value['value']);
            }
        }

        // Determinar o ID para inserção
        $insertId = $hasAutoIncrement ? ($data[$autoIncrementColumn] ?? $nextId) : count($fileData['data']);

        // Atualizar auto_increment_index para o maior valor usado
        if ($hasAutoIncrement) {
            $fileData['columns']['auto_increment_index'] = max(
                $currentAutoIncrementIndex,
                $insertId
            );
        }

        // Inserir dados
        $fileData['data'][$insertId] = $data;

        $this->storage->put("{$database}/{$table}.json", $fileData);
        echo "Data inserted successfully into table {$table}." . PHP_EOL;
    }
}
