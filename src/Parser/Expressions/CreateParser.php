<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class CreateParser extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'CREATE');
        $nextToken = $this->parser->currentToken();

        if ($nextToken['value'] === 'DATABASE') {
            return $this->parseCreateDatabase();
        }

        if ($nextToken['value'] === 'TABLE') {
            return $this->parseCreateTable();
        }

        var_dump($nextToken);

        throw new \Exception("Invalid CREATE statement");
    }

    private function parseCreateDatabase(): array
    {
        $this->parser->consume('KEYWORD', 'DATABASE');
        $dbName = $this->parser->consume('IDENTIFIER');

        return [
            'type' => 'CREATE',
            'redirect' => 'DATABASE',
            'database' => $dbName
        ];
    }

    private function parseCreateTable(): array
    {
        $this->parser->consume('KEYWORD', 'TABLE');
        $tableName = $this->parser->consume('IDENTIFIER');

        // Consumir o parêntese de abertura
        $this->parser->consume('SYMBOL', '(');

        $columns = [];

        // Enquanto não encontrar o parêntese de fechamento
        while ($this->parser->currentToken()['value'] !== ')') {
            // Ler nome da coluna
            $columnName = $this->parser->consume('IDENTIFIER')['value'];

            // Ler tipo da coluna
            $columnType = $this->parser->consume('KEYWORD')['value'];

            // Criar array da definição da coluna
            $column = [
                'name' => $columnName,
                'type' => $columnType,
                'constraints' => []
            ];

            // Verificar se há constraints (PKEY, AUTO_INCREMENT)
            while (
                $this->parser->hasMoreTokens() &&
                $this->parser->currentToken()['type'] === 'KEYWORD' &&
                in_array($this->parser->currentToken()['value'], ['PKEY', 'AUTO_INCREMENT'])
            ) {
                $column['constraints'][] = $this->parser->consume('KEYWORD')['value'];
            }

            $columns[] = $column;

            // Se o próximo token não for vírgula, devemos ter chegado ao final
            if ($this->parser->currentToken()['value'] !== ',') {
                break;
            }

            // Consumir a vírgula e continuar para a próxima coluna
            $this->parser->consume('SYMBOL', ',');
        }

        // Consumir o parêntese de fechamento
        $this->parser->consume('SYMBOL', ')');

        return [
            'type' => 'CREATE',
            'redirect' => 'TABLE',
            'table' => $tableName,
            'columns' => $columns
        ];
    }
}
