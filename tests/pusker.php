<?php

use Fernando\PuskerDB\Pusker\Pusker;

require_once __DIR__ . '/../vendor/autoload.php';

$pusker = new Pusker(database: 'project');

$pusker->query('SELECT age FROM users')->execute();
// $pusker->query('COUNT("SELECT * FROM users WHERE user_id LIKE 1")')->execute();

print_r($pusker->fetch());
// print_r($pusker->fetchAll());

// print_r($pusker->getAST());
// echo $pusker->getLinesAffected();
