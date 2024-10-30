<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final readonly class UseParser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'USE');
        $dbName = $this->parser->parseIdentifier();
        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'USE',
            'database' => $dbName,
        ];
    }
}
