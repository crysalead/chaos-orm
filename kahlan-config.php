<?php
use box\Box;
use chaos\source\database\adapter\MySql;
use chaos\source\database\adapter\PostgreSql;
use chaos\source\database\adapter\Sqlite3;

$box = box('chaos.spec', new Box());

$box->factory('source.database.mysql', function() {
    return new MySql([
        'database' => 'chaos_test',
        'login' => 'root',
        'password' => ''
    ]);
});

$box->factory('source.database.postgresql', function() {
    return new PostgreSql([
        'database' => 'chaos_test',
        'login' => 'root',
        'password' => 'mdp'
    ]);
});

$box->factory('source.database.sqlite3', function() {
    return new Sqlite3([
        'database' => ':memory:'
    ]);
});

?>