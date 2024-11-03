<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;

require_once __DIR__ . '/../vendor/autoload.php';

$input = '
    CREATE DATABASE project;

    USE project;

    CREATE TABLE users (
        id NUMBER AUTO_INCREMENT PKEY,
        age NUMBER,
        name STRING
    );

    INSERT INTO users (id, age, name) VALUES (1, 90, "Fernando");
    INSERT INTO users (age, name) VALUES (19, "Pessoa1");
    INSERT INTO users (age, name) VALUES (15, "Pessoa2");
    INSERT INTO users (id, age, name) VALUES (100, 50, "Jonas");
    INSERT INTO users (age, name) VALUES (20, "Pessoa3");
';

$lexer = new Lexer($input);
$parser = new Parser($lexer->getTokens());

$runtime = new Runtime();
$runtime->run($parser->parse());

// CREATE TABLE users (id INTEGER PKEY AUTO_INCREMENT, name STRING);
