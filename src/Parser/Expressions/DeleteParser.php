<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final readonly class DeleteParser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'DELETE');
        $this->parser->consume('KEYWORD', 'FROM');
        $table = $this->parser->parseIdentifier();

        $conditions = null;

        if ($this->parser->currentToken() && $this->parser->currentToken()['value'] === 'WHERE') {
            $this->parser->consume('KEYWORD', 'WHERE');
            $conditions = $this->parser->parseConditions();
        }

        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'DELETE',
            'table' => $table,
            'conditions' => $conditions,
        ];
    }
}
