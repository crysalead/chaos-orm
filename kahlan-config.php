<?php
use box\Box;
use chaos\source\database\adapter\MySql;
use chaos\source\database\adapter\PostgreSql;
use chaos\source\database\adapter\Sqlite3;

$box = box('chaos.spec', new Box());

$box->service('source.database.mysql', function() {
    return new MySql([
        'database' => 'chaos_test',
        'username' => 'root',
        'password' => 'root'
    ]);
});

$box->service('source.database.postgresql', function() {
    return new PostgreSql([
        'database' => 'chaos_test',
        'username' => 'root',
        'password' => 'root'
    ]);
});

?>