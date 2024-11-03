<?php
namespace Fernando\PuskerDB\Parser\Expressions;

use Fernando\PuskerDB\Parser\Parser;

final class ShowParser extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function getAST(): array
    {
        $this->parser->consume('KEYWORD', 'SHOW');
        $showType = $this->parser->consume('KEYWORD')['value'];

        if (!in_array($showType, ['DATABASES', 'TABLES', 'DATABASE'])) {
            // throw new Exception("Expected 'DATABASES' or 'TABLES', got {$showType}");
        }

        $dbName = null;
        if ($showType === 'TABLES' && $this->parser->currentToken()) {
            $this->parser->consume('KEYWORD', 'FROM');
            $dbName = $this->parser->parseIdentifier();
        }
        $this->parser->consume('SYMBOL', ';');

        return [
            'type' => 'SHOW',
            'show_type' => $showType,
            'database' => $dbName,
        ];
    }
}
