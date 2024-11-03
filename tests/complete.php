<?php

use Fernando\PuskerDB\Lexer\Lexer;
use Fernando\PuskerDB\Parser\Parser;
use Fernando\PuskerDB\Runtime\Runtime;

require_once __DIR__ . '/../vendor/autoload.php';

$input = '
CREATE DATABASE project;

SHOW DATABASE;
SHOW DATABASES;
SHOW TABLES FROM project;

USE project;

CREATE TABLE users (
    id NUMBER PKEY,
    age NUMBER,
    name STRING
);

CREATE TABLE payments (
    id NUMBER PKEY AUTO_INCREMENT,
    payment_id NUMBER,
    user_id NUMBER
);

INSERT INTO users (id, age, name) VALUES (1, 90, "Fernando");
INSERT INTO users (id, age, name) VALUES (100, 50, "Jonas");

INSERT INTO payments (payment_id, user_id) VALUES (199999, 10101010);
INSERT INTO payments (payment_id, user_id) VALUES (155555, 10101010);
INSERT INTO payments (payment_id, user_id) VALUES (202222, 10101010);

SELECT * FROM payments WHERE user_id LIKE 101;
SELECT * FROM payments WHERE payment_id >= 1 AND payment_id <= 202222 AND user_id < 10101010;
SELECT * FROM users WHERE name = "Fernando";
';

$lexer = new Lexer($input);
$parser = new Parser($lexer->getTokens());

$runtime = new Runtime();
$runtime->run($parser->parse());
