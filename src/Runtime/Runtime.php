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
        if (!$astParser) {
            return;
        }

        foreach ($astParser as $_ => $ast) {
            // var_dump($ast);
            $class = "Fernando\\PuskerDB\\Runtime\\Runtimes\\" . ucfirst(strtolower($ast['type'])) . "Runtime";

            if (!class_exists($class)) {
                PuskerException::expect("ERROR: Expect KEYWORD, receive {$class}");
                return;
            }

            // var_dump($class);
            $instanceExpression = new $class($this, $ast, $this->storage);
            $instanceExpression->runRuntime();
        }
    }

    protected function executeConditions(?array $conditions, array $data): array
    {
        $results = [];

        if (!$conditions) {
            return [];
        }

        // Loop para cada linha de dados da tabela
        foreach ($data as $row) {
            $conditionMet = true;
            $lastLogicOperator = null;

            // Loop para cada condição no AST
            foreach ($conditions as $index => $conditionData) {
                $condition = $conditionData['condition'];
                $logicOperator = $conditionData['logic_operator'] ?? null;

                $column = $condition['column'];
                $operator = $condition['operator'];
                $value = $condition['value']['value']; // Extraindo o valor

                // Verificando a condição com base no operador
                $isConditionMet = false;
                switch ($operator) {
                    case '=':
                        $isConditionMet = $row[$column] == $value;
                        break;
                    case '<':
                        $isConditionMet = $row[$column] < $value;
                        break;
                    case '>':
                        $isConditionMet = $row[$column] > $value;
                        break;
                    // Adicione mais operadores conforme necessário
                }

                // Avaliar o operador lógico (AND / OR)
                if ($index === 0) {
                    // Primeira condição, definir o resultado inicial
                    $conditionMet = $isConditionMet;
                } else {
                    if ($lastLogicOperator === 'AND') {
                        $conditionMet = $conditionMet && $isConditionMet;
                    } elseif ($lastLogicOperator === 'OR') {
                        $conditionMet = $conditionMet || $isConditionMet;
                    }
                }

                // Atualizando o último operador lógico
                $lastLogicOperator = $logicOperator;
            }

            // Se a condição foi atendida, adicionar ao resultado
            if ($conditionMet) {
                $results[] = $row;
            }
        }

        return $results;
    }

    public function getInput(): string
    {
        return $this->input;
    }
}
