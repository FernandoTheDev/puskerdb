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
        //        var_dump($this->lexerTokens);
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

    public function parseString(): array
    {
        $token = $this->nextToken();
        if ($token['type'] !== 'STRING') {
            PuskerException::expect("Expected STRING, got {$token['type']}");
        }

        return $token;
    }

    public function parseColumns(): array
    {
        $columns = [];
        // Verifica se a próxima token é '*'
        if ($this->currentToken() && $this->currentToken()['value'] === '*') {
            $columns[] = $this->consume('SYMBOL', '*')['value']; // Adiciona '*' à lista de colunas
            return $columns; // Retorna imediatamente
        }

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
        $logicalOperator = null;

        do {
            // Parse da coluna
            $column = $this->consume('IDENTIFIER')['value'];

            // Parse do operador
            $operator = $this->parseOperator();

            // Parse do valor (que pode ser nulo para IS NULL/IS NOT NULL)
            $value = $this->parseConditionValue($operator);

            // Monta a condição atual
            $currentCondition = [
                'column' => $column,
                'operator' => $operator,
                'value' => $value
            ];

            // Adiciona a condição ao array de condições
            if ($logicalOperator !== null) {
                $conditions[] = [
                    'logic_operator' => $logicalOperator,
                    'condition' => $currentCondition
                ];
            } else {
                $conditions[] = [
                    'condition' => $currentCondition
                ];
            }

            // Verifica se há mais condições (AND/OR)
            if ($this->currentToken() && in_array($this->currentToken()['value'], ['AND', 'OR'])) {
                $logicalOperator = $this->consume('KEYWORD')['value'];
            } else {
                break;
            }

        } while (true);

        return $conditions;
    }

    private function parseOperator(): string
    {
        $token = $this->currentToken();

        // Verifica operadores especiais primeiro (IS NULL, IS NOT NULL)
        if ($token['type'] === 'KEYWORD' && in_array($token['value'], ['IS'])) {
            $this->consume('KEYWORD', 'IS');

            if ($this->currentToken()['type'] === 'KEYWORD' && $this->currentToken()['value'] === 'NOT') {
                $this->consume('KEYWORD', 'NOT');
                $this->consume('KEYWORD', 'NULL');
                return 'IS NOT NULL';
            }

            if ($this->currentToken()['type'] === 'KEYWORD' && $this->currentToken()['value'] === 'NULL') {
                $this->consume('KEYWORD', 'NULL');
                return 'IS NULL';
            }
        }

        // Verifica operadores IN/NOT IN
        if ($token['type'] === 'KEYWORD' && in_array($token['value'], ['IN', 'NOT'])) {
            if ($token['value'] === 'NOT') {
                $this->consume('KEYWORD', 'NOT');
                $this->consume('KEYWORD', 'IN');
                return 'NOT IN';
            } else {
                $this->consume('KEYWORD', 'IN');
                return 'IN';
            }
        }

        // Operadores padrão de comparação
        $validOperators = ['=', '<>', '!=', '<', '>', '<=', '>=', 'LIKE'];

        if (
            $token['type'] === 'SYMBOL' ||
            ($token['type'] === 'KEYWORD' && in_array($token['value'], ['LIKE']))
        ) {
            $operator = $this->nextToken()['value'];
            if (!in_array($operator, $validOperators)) {
                PuskerException::expect("Invalid operator: {$operator}");
            }
            return $operator;
        }

        PuskerException::expect("Expected operator, got {$token['type']} ({$token['value']})");
        return '';
    }

    private function parseConditionValue(string $operator): ?array
    {
        // Para operadores IS NULL e IS NOT NULL, não há valor
        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
            return null;
        }

        // Para operadores IN e NOT IN, parse uma lista de valores
        if (in_array($operator, ['IN', 'NOT IN'])) {
            return $this->parseInList();
        }

        // Para LIKE e operadores padrão, parse um único valor
        return $this->parseValue();
    }

    private function parseInList(): array
    {
        $values = [];

        // Consome o parêntese de abertura
        $this->consume('SYMBOL', '(');

        do {
            $values[] = $this->parseValue();

            if ($this->currentToken()['value'] === ',') {
                $this->consume('SYMBOL', ',');
            } else {
                break;
            }
        } while (true);

        // Consome o parêntese de fechamento
        $this->consume('SYMBOL', ')');

        return [
            'type' => 'LIST',
            'value' => $values
        ];
    }

    public function parseValue(): array
    {
        $token = $this->nextToken();
        if ($token['type'] === 'STRING') {
            $token['value'] = (string) str_ireplace(['"', "'"], '', $token['value']);
            return $token;
        }
        if ($token['type'] === 'NUMBER') {
            $token['value'] = (int) $token['value'];
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
