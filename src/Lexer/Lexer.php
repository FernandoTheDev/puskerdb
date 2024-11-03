<?php
namespace Fernando\PuskerDB\Lexer;

final class Lexer
{
    private string $input;
    private array $queriesTokens = [];
    private const string TOKEN_KEYWORD = 'KEYWORD';
    private const string TOKEN_IDENTIFIER = 'IDENTIFIER';
    private const string TOKEN_NUMBER = 'NUMBER';
    private const string TOKEN_STRING = 'STRING';
    private const string TOKEN_SYMBOL = 'SYMBOL';
    private const string TOKEN_UNKNOWN = 'UNKNOWN';
    private const string TOKEN_FUNCTION = 'FUNCTION';

    private array $keywords = [
        'SELECT',
        'FROM',
        'WHERE',
        'INSERT',
        'INTO',
        'VALUES',
        'UPDATE',
        'SET',
        'USE',
        'DELETE',
        'CREATE',
        'SHOW',
        'DROP',
        'TABLES',
        'TABLE',
        'DATABASES',
        'DATABASE',
        'STRING',
        'INTEGER',
        'TEXT',
        'INT',
        'AND',
        'OR',
        'PKEY',
        'AUTO_INCREMENT',
        'LIKE',
        'IN',
        'NOT IN',
        'IS NULL',
        'IS NOT NULL'
    ];

    private array $functions = [
        'COUNT',
        'SUM',
        'AVG',
        'MIN',
        'MAX',
        'CLEAR'
    ];

    private array $symbols = ['*', '>', '<', '>=', '<=', ',', '=', '(', ')', ';'];

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->tokenize();
    }

    private function tokenize(): void
    {
        $regexParts = [
            '\s+', // Espaços em branco
            '\b(SELECT|LIKE|IN|NOT IN|IS NULL|IS NOT NULL|FROM|WHERE|INSERT|INTO|VALUES|UPDATE|SET|DELETE|CREATE|SHOW|DROP|PKEY|AUTO_INCREMENT|TABLES|TABLE|USE|DATABASE|DATABASES|INTEGER|TEXT|AND|OR)\b', // Palavras-chave
            '\b(COUNT|SUM|AVG|MIN|MAX|CLEAR)\b', // Funções
            '\*|>|<|>=|<=|,|=|;|\(|\)', // Símbolos
            '\'[^\']*\'|\"[^\"]*\"', // Strings
            '[a-zA-Z_][a-zA-Z0-9_]*', // Identificadores
            '\d+', // Números
        ];

        $regex = '/(' . implode('|', $regexParts) . ')/i';

        $queries = explode(';', $this->input);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue; // Pula consultas vazias
            }

            $tokens = [];
            preg_match_all($regex, "{$query};", $matches);

            foreach ($matches[0] as $match) {
                $trimmedMatch = trim($match);

                if ($trimmedMatch === '') {
                    continue; // Pula matches vazios
                }

                $tokens[] = $this->identifyToken($trimmedMatch);
            }

            $this->queriesTokens[] = $tokens;
        }
    }

    private function identifyToken(string|int|float $token): array
    {
        $trimmedToken = trim($token);

        if (in_array(strtoupper($trimmedToken), $this->keywords)) {
            return ['type' => self::TOKEN_KEYWORD, 'value' => strtoupper($trimmedToken)];
        }

        if (in_array(strtoupper($trimmedToken), $this->functions)) {
            return ['type' => self::TOKEN_FUNCTION, 'value' => strtoupper($trimmedToken)];
        }

        if (preg_match('/^\d+$/', $trimmedToken)) {
            return ['type' => self::TOKEN_NUMBER, 'value' => $trimmedToken];
        }

        if (preg_match('/^["\'](.*?)["\']$/', $trimmedToken)) {
            return ['type' => self::TOKEN_STRING, 'value' => substr($trimmedToken, 1, -1)];
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $trimmedToken)) {
            return ['type' => self::TOKEN_IDENTIFIER, 'value' => $trimmedToken];
        }

        if (in_array($trimmedToken, $this->symbols)) {
            return ['type' => self::TOKEN_SYMBOL, 'value' => $trimmedToken];
        }

        return ['type' => self::TOKEN_UNKNOWN, 'value' => $trimmedToken];
    }

    public function getTokens(): array
    {
        return $this->queriesTokens;
    }
}
