<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class UpdateParser extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'UPDATE');
        $table = $this->parser->parseIdentifier();
        $this->parser->consume('KEYWORD', 'SET');
        $updates = $this->parser->parseAssignments();

        $conditions = null;
        if ($this->parser->currentToken() && $this->parser->currentToken()['value'] === 'WHERE') {
            $this->parser->consume('KEYWORD', 'WHERE');
            $conditions = $this->parser->parseConditions();
        }
        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'UPDATE',
            'table' => $table,
            'updates' => $updates,
            'conditions' => $conditions,
        ];
    }
}
