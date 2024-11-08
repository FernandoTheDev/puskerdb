<?php

namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class ClearParser extends Parser
{
    public function __construct(private readonly Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('FUNCTION', 'CLEAR');
        $this->parser->consume('SYMBOL', '(');
        $this->parser->consume('SYMBOL', ')');
        $this->parser->consume('SYMBOL', ';');

        return ['type' => 'CLEAR'];
    }
}
