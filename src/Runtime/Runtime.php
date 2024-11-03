<?php
namespace Fernando\PuskerDB\Runtime;

use Fernando\PuskerDB\Pusker\Pusker;
use Fernando\PuskerDB\Storage\Storage;
use Fernando\PuskerDB\Exception\PuskerException;
use Fernando\PuskerDB\Utils\ConsoleTableUtils;

class Runtime
{
    protected string $database = '';
    protected string $input = '[puskerdb]> ';

    /**  false = return array
     *   true = print response
     */
    protected bool $isCli = false;

    public function __construct(
        private readonly Storage $storage = new Storage(),
        private readonly Pusker $pusker
    ) {
    }

    public function run(array $astParser): array|null
    {
        if (!$astParser) {
            return $this->render([]);
        }

        $bools = [];

        foreach ($astParser as $_ => $ast) {
            $class = "Fernando\\PuskerDB\\Runtime\\Runtimes\\" . ucfirst(strtolower($ast['type'])) . "Runtime";
            if (!class_exists($class)) {
                PuskerException::throw("ERROR: Expect KEYWORD, receive {$class}", []);
                return $this->render([]);
            }
            $instanceExpression = new $class($this, $ast, $this->storage);
            $bools[] = $instanceExpression->runRuntime();
        }

        return $this->render($bools);
    }

    private function render(array $data): array
    {
        if ($this->isCli) {
            $cli = [];
            foreach ($data as $rows) {
                $cli[] = $rows;
            }
            return $cli;
        }

        $output = [];
        foreach ($data as $row) {
            $output[] = $row;
        }
        return $output;
    }

    protected function executeConditions(?array $conditions, array $data): array
    {
        if (!$conditions) {
            return $data;
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

        foreach ($conditions as $conditionData) {
            $currentResult = $this->evaluateSingleCondition($conditionData['condition'], $row);
            $logicOperator = $conditionData['logic_operator'] ?? null;

            $result = $result === null
                ? $currentResult
                : $this->applyLogicOperator($result, $currentResult, $lastLogicOperator);

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
        $value = is_numeric($value) ? $this->normalizeValue($value, $condition['value']['type']) : $value;
        $rowValue = is_string($rowValue) ? (float) $rowValue : $rowValue;

        return match ($operator) {
            '=' => $rowValue == $value,
            '<>', '!=' => $rowValue != $value,
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
        $pattern = '/' . str_replace(['%', '_'], ['.*', '.'], $pattern) . '/i';
        return (bool) preg_match($pattern, $value);
    }

    private function evaluateInCondition($value, array $list): bool
    {
        return in_array($value, $list, true);
    }

    private function normalizeValue($value, string $type)
    {
        return match ($type) {
            'NUMBER' => (int) $value,
            default => (float) $value,
        };
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function setDatabase(string $database): void
    {
        $this->database = $database;
    }

    public function setCli(bool $cli): void
    {
        $this->isCli = $cli;
    }
}
