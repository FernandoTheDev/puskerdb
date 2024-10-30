<?php
namespace Fernando\PuskerDB\Parser;

use Fernando\PuskerDB\Exception\PuskerException;

final class Parser
{
//    const array TOKEN_TYPES = [
//        'KEYWORD' => 'T_KEYWORD',
//        'IDENTIFIER' => 'T_IDENTIFIER',
//        'NUMBER' => 'T_NUMBER',
//        'STRING' => 'T_STRING',
//        'SYMBOL' => 'T_SYMBOL',
//    ];

    protected int $currentTokenIndex = 0;
    protected array $tokens = [];
    private array $lexerTokens;

    public function __construct(array $lexerTokens)
    {
        $this->lexerTokens = $lexerTokens;
    }

    public function parse(): array
    {
        var_dump($this->lexerTokens);
        if (!$this->lexerTokens) {
            return [];
        }

        $data = [];

        foreach ($this->lexerTokens as $_ => $token) {
            $this->currentTokenIndex = 0;
            $class = "Fernando\\PuskerDB\\Parser\\Expressions\\" . ucfirst(strtolower($token[0]['value'])) . "Parser";

            if (!class_exists($class)) {
                PuskerException::expect("ERROR: Expect KEYWORD, receive {$token[0]['type']} -> '{$token[0]['value']}'");
                return [];
            }

            $this->tokens = $token;
            $instanceExpression = new $class($this);
            $data[] = $instanceExpression->getAST();
        }

        return $data;
    }

    public function parseFunctionArguments(): array
    {
        $arguments = [];
        do {
            $arguments[] = $this->parseValue();
            if ($this->currentToken() && $this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break;
            }
        } while (true);

        return $arguments;
    }

    public function parseColumnDefinitions(): array
    {
        $columns = [];

        do {
            // Nome da coluna
            $columnName = $this->consume('IDENTIFIER')['value'];

            // Tipo da coluna
            $columnType = $this->consume('KEYWORD')['value'];

            // Initialization o array de definição da coluna
            $columnDefinition = [
                'name' => $columnName,
                'type' => $columnType,
                'constraints' => [] // Para armazenar modificadores como PKEY, NOT NULL, etc.
            ];

            // Verificar por modificadores adicionais (PKEY, NOT NULL, etc.)
            $constraintCount = 0; // Contador para os modificadores

            while ($this->currentToken() && in_array($this->currentToken()['value'], ['PKEY', 'AUTO_INCREMENT'])) {
                if ($constraintCount < 2) { // Limita a 2 modificadores
                    $constraint = $this->consume('KEYWORD')['value'];
                    $columnDefinition['constraints'][] = $constraint;
                    $constraintCount++;
                } else {
                    break; // Sai do loop se já tiver 2 modificadores
                }
            }

            // Adiciona a definição da coluna ao array de colunas
            $columns[] = $columnDefinition;

            // Verifica se há uma vírgula, se sim, consome e continua para a próxima coluna
            if ($this->currentToken() && $this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break; // Sai do loop se não houver mais colunas
            }
        } while (true);

        return $columns;
    }

    public function parseIdentifier(): array
    {
        $token = $this->nextToken();
        if ($token['type'] !== 'IDENTIFIER') {
            PuskerException::expect("Expected T_IDENTIFIER, got {$token['type']}");
        }

        return $token;
    }

    public function parseColumns(): array
    {
        $columns = [];
        do {
            $columns[] = $this->consume('IDENTIFIER')['value'];
            if ($this->currentToken() && $this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break;
            }
        } while (true);

        return $columns;
    }

    public function parseValues(): array
    {
        $values = [];
        do {
            $values[] = $this->parseValue();
            if ($this->currentToken() && $this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break;
            }
        } while (true);

        return $values;
    }

    public function parseAssignments(): array
    {
        $assignments = [];
        do {
            $column = $this->consume('IDENTIFIER')['value'];
            $this->consume('SYMBOL', '=');
            $value = $this->parseValue();
            $assignments[] = ['column' => $column, 'value' => $value];
            if ($this->currentToken() && $this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break;
            }
        } while (true);

        return $assignments;
    }

    public function parseConditions(): array
    {
        $conditions = [];
//        $currentCondition = [];
        $logicalOperator = null; // Variável para armazenar o operador lógico atual

        do {
            // Consome uma condição (coluna, operador e valor)
            $column = $this->consume('IDENTIFIER')['value'];
            $operator = $this->consume('SYMBOL')['value'];
            $value = $this->parseValue();

            // Adiciona a condição atual
            $currentCondition = [
                'column' => $column,
                'operator' => $operator,
                'value' => $value
            ];

            // Se já há condições, adiciona o operador lógico antes da próxima
            if ($logicalOperator !== null) {
                $conditions[] = [
                    'logic_operator' => $logicalOperator,
                    'condition' => $currentCondition
                ];
            } else {
                // Para a primeira condição, não existe operador lógico anterior
                $conditions[] = [
                    'condition' => $currentCondition
                ];
            }

            // Verifica o próximo operador lógico: AND ou OR
            if ($this->currentToken() && in_array($this->currentToken()['value'], ['AND', 'OR'])) {
                $logicalOperator = $this->consume('KEYWORD')['value'];
            } else {
                break; // Se não houver AND ou OR, termina o loop
            }

        } while (true);

        return $conditions;
    }

    public function parseValue(): array
    {
        $token = $this->nextToken();
        if ($token['type'] === 'STRING' || $token['type'] === 'NUMBER') {
            return $token;
        }
        PuskerException::expect("Expected value, got {$token['type']}");
        return [];
    }

    public function consume(string $type, string $value = null): array
    {
        $token = $this->nextToken();
        // var_dump($token);
        if ($token['type'] !== $type || ($value !== null && $token['value'] !== $value)) {
            PuskerException::expect(sprintf("Expected %s '%s', got %s '%s'", $type, $value, $token['type'], $token['value']));
        }

        return $token;
    }

    public function currentToken(): ?array
    {
        return $this->tokens[$this->currentTokenIndex] ?? null;
    }

    public function nextToken(): array
    {
        if (!isset($this->tokens[$this->currentTokenIndex])) {
            PuskerException::expect("Unexpected end of input");
            return ['type' => 'UNKNOW', 'value' => ''];
        }

        return $this->tokens[$this->currentTokenIndex++];
    }
}
