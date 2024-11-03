<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class DropParser extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'DROP');
        $showType = $this->parser->consume('KEYWORD')['value'];

        if (!in_array($showType, ['DATABASE', 'TABLE'])) {
            // throw new Exception("Expected 'DATABASES' or 'TABLES', got {$showType}");
        }

        $alvo = $this->parser->parseIdentifier();

        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'DROP',
            'drop_type' => $showType,
            'alvo' => $alvo,
        ];
    }
}
