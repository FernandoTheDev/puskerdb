<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final readonly class CreateParser
{
    public function __construct(private Parser $parser)
    {
    }

    private function parseCreateTable(): array
    {
        $table = $this->parser->parseIdentifier();
        $this->parser->consume('SYMBOL', '(');
        $columns = $this->parser->parseColumnDefinitions();
        $this->parser->consume('SYMBOL', ')');
        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'CREATE',
            'redirect' => 'TABLE',
            'table' => $table,
            'columns' => $columns,
        ];
    }

    private function parseCreateDatabase(): array
    {
        $database = $this->parser->parseIdentifier();
        $this->parser->consume('SYMBOL', ';');
        return [
            'type' => 'CREATE',
            'redirect' => 'DATABASE',
            'database' => $database,
        ];
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'CREATE');
        $objectType = $this->parser->consume('KEYWORD')['value'];

        if ($objectType === 'TABLE') {
            return $this->parseCreateTable();
        } elseif ($objectType === 'DATABASE') {
            return $this->parseCreateDatabase();
        }

        return [];
    }
}
