<?php
namespace Fernando\PuskerDB\Runtime;
use Fernando\PuskerDB\Exception\PuskerException;
use Fernando\PuskerDB\Storage\Storage;

class Runtime
{
    protected string $database = '';
    protected string $input = '[puskerdb]> ';

    public function __construct(
        private readonly Storage $storage = new Storage()
    ) {
    }

    public function run(array $astParser): void
    {
        // echo json_encode($astParser, JSON_PRETTY_PRINT) . PHP_EOL;
        if (!$astParser) {
            return;
        }

        foreach ($astParser as $_ => $ast) {
            $class = "Fernando\\PuskerDB\\Runtime\\Runtimes\\" . ucfirst(strtolower($ast['type'])) . "Runtime";
            if (!class_exists($class)) {
                PuskerException::expect("ERROR: Expect KEYWORD, receive {$class}");
                return;
            }
            $instanceExpression = new $class($this, $ast, $this->storage);
            $instanceExpression->runRuntime();
        }
    }

    protected function executeConditions(?array $conditions, array $data): array
    {
        if (!$conditions) {
            return $data; // Retorna todos os dados se não houver condições
        }

        $results = [];
        foreach ($data as $row) {
            if ($this->evaluateConditionGroup($conditions, $row)) {
                $results[] = $row;
            }
        }

        return $results;
    }

    private function evaluateConditionGroup(array $conditions, array $row): bool
    {
        $result = null;
        $lastLogicOperator = null;

        foreach ($conditions as $index => $conditionData) {
            $condition = $conditionData['condition'];
            $currentResult = $this->evaluateSingleCondition($condition, $row);
            $logicOperator = $conditionData['logic_operator'] ?? null;

            if ($result === null) {
                $result = $currentResult;
            } else {
                $result = $this->applyLogicOperator($result, $currentResult, $lastLogicOperator);
            }

            $lastLogicOperator = $logicOperator;
        }

        return $result ?? false;
    }

    private function evaluateSingleCondition(array $condition, array $row): bool
    {
        $column = $condition['column'];
        $operator = $condition['operator'];
        $value = $condition['value']['value'] ?? null;

        if (!isset($row[$column])) {
            return false;
        }

        $rowValue = $row[$column];

        // Converter para o mesmo tipo antes da comparação
        if (is_numeric($value)) {
            $value = $condition['value']['type'] === 'NUMBER' ? (int) $value : (float) $value;
            $rowValue = (is_string($rowValue)) ? (float) $rowValue : $rowValue;
        }

        return match ($operator) {
            '=' => $rowValue == $value,
            '<>' => $rowValue != $value,
            '!=' => $rowValue != $value,
            '<' => $rowValue < $value,
            '>' => $rowValue > $value,
            '<=' => $rowValue <= $value,
            '>=' => $rowValue >= $value,
            'LIKE' => $this->evaluateLikeCondition($rowValue, $value),
            'IN' => $this->evaluateInCondition($rowValue, $value),
            'NOT IN' => !$this->evaluateInCondition($rowValue, $value),
            'IS NULL' => is_null($rowValue),
            'IS NOT NULL' => !is_null($rowValue),
            default => false,
        };
    }

    private function applyLogicOperator(bool $leftResult, bool $rightResult, ?string $operator): bool
    {
        return match (strtoupper($operator)) {
            'AND' => $leftResult && $rightResult,
            'OR' => $leftResult || $rightResult,
            default => $rightResult,
        };
    }

    private function evaluateLikeCondition(string $value, string $pattern): bool
    {
        // Substitui '%' por '.*' para correspondência de zero ou mais caracteres
        // e '_' por '.' para corresponder a um único caractere
        $pattern = str_replace(['%', '_'], ['.*', '.'], $pattern);
        $pattern = '/' . $pattern . '/i';  // O 'i' é para tornar a busca case insensitive
        return (bool) preg_match($pattern, $value);
    }


    private function evaluateInCondition($value, array $list): bool
    {
        return in_array($value, $list, true);
    }

    public function getInput(): string
    {
        return $this->input;
    }
}
