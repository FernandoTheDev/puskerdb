<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;

require_once __DIR__ . '/../vendor/autoload.php';

$runtime = new Runtime();

while (true) {
    $input = readline($runtime->getInput());

    $lexer = new Lexer($input);
    $parser = new Parser($lexer->getTokens());
    $runtime->run($parser->parse());
}
