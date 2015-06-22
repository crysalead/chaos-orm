<?php
namespace chaos\spec\suite;

use PDO;
use chaos\source\database\adapter\MySql;
use chaos\source\Schema;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("MySql", function() {

    beforeEach(function() {
        $this->adapter = box('chaos.spec')->get('source.database.mysql');
        $this->fixtures = new Fixtures([
            'connection' => $this->adapter,
            'fixtures'   => [
                'gallery' => 'chaos\spec\fixture\schema\Gallery'
            ]
        ]);
    });

    afterEach(function() {
        $this->fixtures->drop();
    });

    describe("->sources()", function() {

        it("shows sources", function() {

            $this->fixtures->populate('gallery');
            $sources = $this->adapter->sources();

            expect($sources)->toBe([
                'gallery' => 'gallery'
            ]);

        });

    });

    describe("->describe()", function() {

        it("describe a source", function() {

            $this->fixtures->populate('gallery');

            $schema = $this->adapter->describe('gallery');

            expect($schema->field('id'))->toBe([
                'type' => 'integer',
                'length' => 11,
                'null' => false,
                'default' => null,
                'array' => false
            ]);

            expect($schema->field('name'))->toBe([
                'type' => 'string',
                'length' => 255,
                'null' => true,
                'default' => null,
                'array' => false
            ]);

            //$statement = $this->adapter->execute("SELECT row_to_json(aa) FROM aa");
            //print_r($statement->fetchAll(PDO::FETCH_ASSOC));

            // $statement = $this->adapter->execute("SELECT * FROM authors LEFT JOIN tags ON authors.id = tags.author_id");
            // print_r($statement->fetchAll(PDO::FETCH_NUM));


// "column_name" AS "field", "data_type" AS "type", ';
// $sql .= '"is_nullable" AS "null", "column_default" AS "default", ';
// $sql .= '"character_maximum_length" AS "char_length"

            //$statement = $this->adapter->execute("select * from INFORMATION_SCHEMA.COLUMNS where table_name = 'bb'");

            // $statement = $this->adapter->execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'chaos_test'");
            //print_r($statement->fetchAll(PDO::FETCH_ASSOC));

            // print_r($this->adapter->sources());
        });

    });

});

?>