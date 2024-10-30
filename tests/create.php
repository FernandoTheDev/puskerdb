<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;

require_once __DIR__ . '/../vendor/autoload.php';

$input = '
    CREATE DATABASE project;

    USE project;

    CREATE TABLE users (
        id NUMBER PKEY,
        name STRING
    );

    INSERT INTO users (id, name) VALUES (1, "Fernando");
';

$lexer = new Lexer($input);
$parser = new Parser($lexer->getTokens());

$runtime = new Runtime();
$runtime->run($parser->parse());

// CREATE TABLE users (id INTEGER PKEY AUTO_INCREMENT, name STRING);
