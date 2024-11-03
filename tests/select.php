<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;

require_once __DIR__ . '/../vendor/autoload.php';

$input = <<<'SQL'
    USE project;
    
    SELECT name FROM users;
SQL;

$lexer = new Lexer($input);
$parser = new Parser($lexer->getTokens());

$runtime = new Runtime();
var_dump($runtime->run($parser->parse()));
// use project; insert into users (id, name) values ("Fernando");
