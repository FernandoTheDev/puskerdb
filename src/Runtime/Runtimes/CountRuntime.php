<?php
namespace Fernando\PuskerDB\Runtime\Runtimes;

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Storage\Storage;

final class CountRuntime extends Runtime
{
    public function __construct(private readonly Runtime $runtime, private readonly array $ast, protected Storage $storage)
    {
    }

    public function runRuntime(): void
    {
        $lexer = new Lexer($this->ast['query']['value']);
        $parser = new Parser($lexer->getTokens());
        $this->runtime->run($parser->parse());
        // echo json_encode($this->ast, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
