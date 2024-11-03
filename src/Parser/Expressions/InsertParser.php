<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class InsertParser extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'INSERT');
        $this->parser->consume('KEYWORD', 'INTO');
        $table = $this->parser->parseIdentifier();

        $this->parser->consume('SYMBOL', '(');
        $columns = $this->parser->parseColumns();
        $this->parser->consume('SYMBOL', ')');

        // var_dump($this->parser->currentToken());

        $this->parser->consume('KEYWORD', 'VALUES');

        // var_dump($this->parser->currentToken());

        // $this->parser->consume('SYMBOL', '(');
        $values = $this->parser->parseValues();
        // $this->parser->consume('SYMBOL', ')');
        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'INSERT',
            'table' => $table,
            'columns' => $columns,
            'values' => $values,
        ];
    }
}
