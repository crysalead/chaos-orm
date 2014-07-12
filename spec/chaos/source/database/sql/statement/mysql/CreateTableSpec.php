<?php
namespace spec\chaos\source\database\sql\statement\mysql;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("CreateTable", function() {

    beforeEach(function() {
        global $gdic;
        $box = $gdic['spec'];
        $this->adapter = $box->get('source.database.mysql');
        $this->create = $this->adapter->sql()->statement('create table');
    });

    describe("create a table", function() {

        it("generates a CREATE table statement with specific metas", function() {
            $this->create->table('table1')
                ->columns([
                    'population' => ['type' => 'integer'],
                    'city' => ['type' => 'string', 'length' => 255, 'null' => false]
                ]);

            $expected  = 'CREATE TABLE `table1` (`population` int, `city` varchar(255) NOT NULL)';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with primary key constraint if an id column is present", function() {
            $this->create->table('table1')
                ->columns([
                    'id' => ['type' => 'serial']
                ]);

            $expected  = 'CREATE TABLE `table1` (`id` int NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with specific metas", function() {
            $this->create->table('table1')
                ->columns([
                    'id' => ['type' => 'id']
                ])
                ->metas([
                    'charset' => 'utf8',
                    'collate' => 'utf8_unicode_ci',
                    'engine' => 'InnoDB',
                    'tablespace' => 'myspace'
                ]);

            $expected  = 'CREATE TABLE `table1` (`id` int)';
            $expected .= ' DEFAULT CHARSET utf8 COLLATE utf8_unicode_ci ENGINE InnoDB TABLESPACE myspace';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a primary key constraint", function() {
            $this->create->table('table1')
                ->columns([
                    'email' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'primary', 'column' => 'email']
                ]);

            $expected  = 'CREATE TABLE `table1` (`email` varchar(255), PRIMARY KEY (`email`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a mulit key primary key constraint", function() {
            $this->create->table('table1')
                ->columns([
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'primary', 'column' => ['firstname', 'lastname']]
                ]);

            $expected  = 'CREATE TABLE `table1` (`firstname` varchar(255), `lastname` varchar(255), PRIMARY KEY (`firstname`, `lastname`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a CHECK constraint", function() {

            $this->create->table('table1')
                ->columns([
                    'population' => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'length' => 255]
                ])
                ->constraints([
                    [
                        'type' => 'check',
                        'expr' => [
                            'population' => ['>' => '20'],
                            'name' => 'Los Angeles'
                        ]
                    ]
                ]);

            $expected  = "CREATE TABLE `table1` (`population` int, `name` varchar(255),";
            $expected .= " CHECK (`population` > 20 AND `name` = 'Los Angeles'))";
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a named CHECK constraint", function() {

            $this->create->table('table1')
                ->columns([
                    'population' => ['type' => 'integer']
                ])
                ->constraints([
                    [
                        'type' => 'check',
                        'constraint' => 'pop',
                        'expr' => [
                            'population' => ['>' => '20']
                        ]
                    ]
                ]);

            $expected  = "CREATE TABLE `table1` (`population` int, CONSTRAINT `pop` CHECK (`population` > 20))";
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a UNIQUE constraint", function() {
             $this->create->table('table1')
                ->columns([
                    'email' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'unique', 'column' => 'email']
                ]);

            $expected  = 'CREATE TABLE `table1` (`email` varchar(255), UNIQUE (`email`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a UNIQUE constraint", function() {
             $this->create->table('table1')
                ->columns([
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'unique', 'column' => ['firstname', 'lastname'] ]
                ]);

            $expected  = 'CREATE TABLE `table1` (`firstname` varchar(255), `lastname` varchar(255), UNIQUE (`firstname`, `lastname`))';
            expect($this->create->toString())->toBe($expected);
        });

         it("generates a CREATE table statement with a UNIQUE INDEX constraint", function() {
             $this->create->table('table1')
                ->columns([
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'unique', 'column' => ['firstname', 'lastname'], 'index' => true ]
                ]);

            $expected  = 'CREATE TABLE `table1` (`firstname` varchar(255), `lastname` varchar(255), UNIQUE INDEX (`firstname`, `lastname`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a UNIQUE KEY constraint if both index & key are set", function() {
             $this->create->table('table1')
                ->columns([
                    'firstname' => ['type' => 'string'],
                    'lastname' => ['type' => 'string']
                ])
                ->constraints([
                    ['type' => 'unique', 'column' => ['firstname', 'lastname'], 'index' => true, 'key' => true ]
                ]);

            $expected  = 'CREATE TABLE `table1` (`firstname` varchar(255), `lastname` varchar(255), UNIQUE KEY (`firstname`, `lastname`))';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with a FOREIGN KEY constraint", function() {
             $this->create->table('table1')
                ->columns([
                    'id' => ['type' => 'id'],
                    'user_id' => ['type' => 'integer']
                ])
                ->constraints([
                    [
                        'type' => 'foreign key',
                        'foreignKey' => 'user_id',
                        'to' => 'user',
                        'primaryKey' => 'id',
                        'on' => 'DELETE CASCADE'
                    ]
                ]);

            $expected  = 'CREATE TABLE `table1` (`id` int, `user_id` int,';
            $expected .= ' FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE)';
            expect($this->create->toString())->toBe($expected);
        });

        it("generates a CREATE table statement with columns metas & constraints", function() {

            $this->create->table('table1')
                ->columns([
                    'id' => ['type' => 'serial'],
                    'table_id' => ['type' => 'integer'],
                    'published' => [
                        'type' => 'datetime',
                        'null' => false,
                        'default' => (object) 'CURRENT_TIMESTAMP'
                    ],
                    'decimal' => [
                        'type' => 'float',
                        'length' => 10,
                        'precision' => 2
                    ],
                    'integer' => [
                        'type' => 'integer',
                        'use' => 'numeric',
                        'length' => 10,
                        'precision' => 2
                    ],
                    'date' => [
                        'type' => 'date',
                        'null' => false,
                    ],
                    'text' => [
                        'type' => 'text',
                        'null' => false,
                    ]
                ])
                ->metas([
                    'charset' => 'utf8',
                    'collate' => 'utf8_unicode_ci',
                    'engine' => 'InnoDB'
                ])
                ->constraints([
                    [
                        'type' => 'check',
                        'expr' => [
                           'integer' => ['<' => 10]
                        ]
                    ],
                    [
                        'type' => 'foreign key',
                        'foreignKey' => 'table_id',
                        'to' => 'other_table',
                        'primaryKey' => 'id',
                        'on' => 'DELETE NO ACTION'
                    ]
                ]);

            $expected = 'CREATE TABLE `table1` (';
            $expected .= '`id` int NOT NULL AUTO_INCREMENT,';
            $expected .= ' `table_id` int,';
            $expected .= ' `published` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,';
            $expected .= ' `decimal` decimal(10,2),';
            $expected .= ' `integer` numeric(10,2),';
            $expected .= ' `date` date NOT NULL,';
            $expected .= ' `text` text NOT NULL,';
            $expected .= ' CHECK (`integer` < 10),';
            $expected .= ' FOREIGN KEY (`table_id`) REFERENCES `other_table` (`id`) ON DELETE NO ACTION,';
            $expected .= ' PRIMARY KEY (`id`))';
            $expected .= ' DEFAULT CHARSET utf8 COLLATE utf8_unicode_ci ENGINE InnoDB';
            expect($this->create->toString())->toBe($expected);
        });

    });




});

?>