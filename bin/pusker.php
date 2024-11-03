<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;
use Fernando\PuskerDB\Utils\ClockUtils;

require_once __DIR__ . '/../vendor/autoload.php';

$runtime = new Runtime();
$clock = new ClockUtils(microtime(true));

$query = '';

while (true) {
    $input = readline($query === '' ? $runtime->getInput() : ' > ');

    if ($input == '') {
        continue;
    }

    $query .= "{$input}\n";

    if (trim($input)[-1] == ';') {
        $query = rtrim($query);
        $query = substr($query, 0, -1);

        $clock->now();

        $lexer = new Lexer($query);
        $parser = new Parser($lexer->getTokens());
        $runtime->run($parser->parse());

        $clock->finish();
        $query = '';
    }
}
