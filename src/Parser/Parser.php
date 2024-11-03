<?php

namespace Fernando\PuskerDB\Parser;

use Fernando\PuskerDB\Exception\PuskerException;
use InvalidArgumentException;

class Parser
{
    private const TOKEN_TYPES = [
        'KEYWORD',
        'IDENTIFIER',
        'NUMBER',
        'STRING',
        'SYMBOL',
    ];

    private const KEYWORD_OPERATORS = [
        'LIKE',
        'IN',
        'NOT IN',
        'IS NULL',
        'IS NOT NULL'
    ];

    private const SYMBOL_OPERATORS = [
        '=',
        '<>',
        '!=',
        '<',
        '>',
        '<=',
        '>='
    ];

    private const COLUMN_CONSTRAINTS = [
        'PKEY',
        'AUTO_INCREMENT'
    ];

    private int $currentTokenIndex = 0;
    private array $tokens = [];
    private array $lexerTokens;

    public function __construct(array $lexerTokens)
    {
        $this->validateLexerTokens($lexerTokens);
        $this->lexerTokens = $lexerTokens;
    }

    private function validateLexerTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (!is_array($token) || empty($token)) {
                throw new InvalidArgumentException('Invalid token format in lexer tokens');
            }
        }
    }

    public function parse(): array
    {
        if (empty($this->lexerTokens)) {
            return [];
        }

        $ast = [];
        // var_dump($this->lexerTokens);

        foreach ($this->lexerTokens as $token) {
            $this->currentTokenIndex = 0;  // Reset do índice
            $this->tokens = $token;
            $statement = $this->parseStatement($token[0]);

            if ($statement) {
                $ast[] = $statement;
            }
        }

        return $ast;
    }

    private function parseStatement(array $token): array
    {
        $keyword = $token['value'] ?? '';

        if ($token['type'] === 'KEYWORD') {
            $keyword = $token['value'];
        }

        $parserClass = $this->getParserClass($keyword);
        // print_r($parserClass);

        if (!class_exists($parserClass)) {
            throw new PuskerException("Unsupported SQL statement: {$keyword}");
        }

        return (new $parserClass($this))->getAST();
    }

    private function isCompoundStatement(string $keyword): bool
    {
        return in_array($keyword, ['CREATE', 'SHOW', 'DROP']);
    }

    private function getParserClass(string $keyword): string
    {
        // Handle compound statements
        return "Fernando\\PuskerDB\\Parser\\Expressions\\" . ucfirst(strtolower($keyword)) . "Parser";
    }

    public function parseIdentifier(): array
    {
        $token = $this->nextToken();
        if ($token['type'] !== 'IDENTIFIER') {
            throw new PuskerException("Expected IDENTIFIER, got {$token['type']}");
        }
        return $token;
    }

    public function parseString(): array
    {
        $token = $this->nextToken();
        if ($token['type'] !== 'STRING') {
            throw new PuskerException("Expected STRING, got {$token['type']}");
        }
        return $this->parseStringValue($token);
    }

    public function parseFunctionArguments(): array
    {
        $arguments = [];
        $this->consume('SYMBOL', '(');

        if ($this->currentToken()['value'] !== ')') {
            do {
                $arguments[] = $this->parseValue();

                if ($this->currentToken()['value'] === ')') {
                    break;
                }

                $this->consume('SYMBOL', ',');
            } while (true);
        }

        $this->consume('SYMBOL', ')');
        return $arguments;
    }

    public function parseColumnDefinitions(): array
    {
        $columns = [];
        $this->consume('SYMBOL', '(');

        do {
            $columns[] = $this->parseColumnDefinition();

            if ($this->currentToken()['value'] === ')') {
                break;
            }

            $this->consume('SYMBOL', ',');
        } while (true);

        $this->consume('SYMBOL', ')');
        return $columns;
    }

    private function parseColumnDefinition(): array
    {
        $columnName = $this->consume('IDENTIFIER')['value'];
        $columnType = $this->consume('KEYWORD')['value'];

        $constraints = [];
        while ($this->hasMoreTokens() && $this->isValidConstraint()) {
            $constraints[] = $this->consume('KEYWORD')['value'];
        }

        return [
            'name' => $columnName,
            'type' => $columnType,
            'constraints' => $constraints
        ];
    }

    public function parseColumns(): array
    {
        if ($this->currentToken()['value'] === '*') {
            return [$this->consume('SYMBOL', '*')['value']];
        }

        $columns = [];
        do {
            $columns[] = $this->consume('IDENTIFIER')['value'];

            if (!$this->hasMoreTokens() || $this->currentToken()['value'] !== ',') {
                break;
            }

            $this->consume('SYMBOL', ',');
        } while (true);

        return $columns;
    }

    public function parseValues(): array
    {
        $values = [];
        $this->consume('SYMBOL', '(');

        do {
            $values[] = $this->parseValue();

            if ($this->currentToken()['value'] === ')') {
                break;
            }

            $this->consume('SYMBOL', ',');
        } while (true);

        $this->consume('SYMBOL', ')');
        return $values;
    }

    public function parseConditions(): array
    {
        $conditions = [];
        $logicalOperator = null;

        do {
            $condition = $this->parseCondition();

            if ($logicalOperator) {
                $conditions[] = [
                    'logic_operator' => $logicalOperator,
                    'condition' => $condition
                ];
            } else {
                $conditions[] = ['condition' => $condition];
            }

            if (
                !$this->hasMoreTokens() ||
                !in_array($this->currentToken()['value'], ['AND', 'OR'])
            ) {
                break;
            }

            $logicalOperator = $this->consume('KEYWORD')['value'];
        } while (true);

        return $conditions;
    }

    private function parseCondition(): array
    {
        $column = $this->consume('IDENTIFIER')['value'];
        $operator = $this->parseOperator();
        var_dump($operator);
        $value = null;

        if (!in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
            $value = $this->parseValue();
        }

        return [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
    }

    private function parseOperator(): string
    {
        $token = $this->currentToken();

        // Se for um operador keyword
        if ($token['type'] === 'KEYWORD') {
            if ($token['value'] === 'IS') {
                return $this->parseIsNullOperator();
            }

            if ($token['value'] === 'LIKE') {
                $this->currentTokenIndex++; // Consome o token LIKE
                return 'LIKE';
            }

            if ($token['value'] === 'IN' || $token['value'] === 'NOT') {
                return $this->parseInOperator();
            }
        }

        // Se for um operador símbolo
        if ($token['type'] === 'SYMBOL' && in_array($token['value'], self::SYMBOL_OPERATORS)) {
            return $this->nextToken()['value'];
        }

        throw new PuskerException("Invalid operator: {$token['value']}");
    }

    private function parseIsNullOperator(): string
    {
        $this->consume('KEYWORD', 'IS');

        if ($this->currentToken()['value'] === 'NOT') {
            $this->consume('KEYWORD', 'NOT');
            $this->consume('KEYWORD', 'NULL');
            return 'IS NOT NULL';
        }

        $this->consume('KEYWORD', 'NULL');
        return 'IS NULL';
    }

    private function parseInOperator(): string
    {
        if ($this->currentToken()['value'] === 'NOT') {
            $this->consume('KEYWORD', 'NOT');
            $this->consume('KEYWORD', 'IN');
            return 'NOT IN';
        }

        $this->consume('KEYWORD', 'IN');
        return 'IN';
    }

    public function parseValue(): array
    {
        $token = $this->nextToken();

        switch ($token['type']) {
            case 'STRING':
                return $this->parseStringValue($token);
            case 'NUMBER':
                return $this->parseNumberValue($token);
            case 'IDENTIFIER':
                return $token;
            default:
                throw new PuskerException("Invalid value type: {$token['type']} -> {$token['value']}");
        }
    }

    private function parseStringValue(array $token): array
    {
        $token['value'] = trim($token['value'], '"\'');
        return $token;
    }

    private function parseNumberValue(array $token): array
    {
        $token['value'] = (int) $token['value'];
        return $token;
    }

    public function consume(string $type, ?string $value = null): array
    {
        if (!$this->hasMoreTokens()) {
            throw new PuskerException('Unexpected end of input');
        }

        // var_dump($this->currentToken());
        $token = $this->nextToken();
        // var_dump($token);

        if (
            $token['type'] !== $type ||
            ($value !== null && $token['value'] !== $value)
        ) {
            throw new PuskerException(
                "Expected {$type} " . ($value ? "'{$value}'" : '') .
                ", got {$token['type']} '{$token['value']}'"
            );
        }

        return $token;
    }

    public function currentToken(): ?array
    {
        return $this->tokens[$this->currentTokenIndex] ?? null;
    }

    /**
     * Advances to the next token in the list and returns it.
     *
     * @return array The next token in the list.
     * @throws PuskerException if there are no more tokens to process.
     */
    public function nextToken(): array
    {
        if (!$this->hasMoreTokens()) {
            throw new PuskerException("Unexpected end of input");
        }
        return $this->tokens[$this->currentTokenIndex++];
    }

    protected function hasMoreTokens(): bool
    {
        return isset($this->tokens[$this->currentTokenIndex]);
    }

    private function isValidConstraint(): bool
    {
        $token = $this->currentToken();
        return $token &&
            $token['type'] === 'KEYWORD' &&
            in_array($token['value'], self::COLUMN_CONSTRAINTS);
    }
}
