<?php

namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final readonly class ClearParser
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

        shell_exec('clear');
        return [];
    }
}