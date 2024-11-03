<?php

namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final readonly class CountParser
{
    public function __construct(private readonly Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('FUNCTION', 'COUNT');
        $this->parser->consume('SYMBOL', '(');
        $query = $this->parser->parseString();
        $this->parser->consume('SYMBOL', ')');
        $this->parser->consume('SYMBOL', ';');

        return ['type' => 'COUNT', 'query' => $query];
    }
}
