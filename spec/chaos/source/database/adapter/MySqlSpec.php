<?php
namespace spec\chaos;

use PDO;
use chaos\source\database\adapter\MySql;
use chaos\source\Schema;

use kahlan\plugin\Stub;

describe("MySql", function() {

    before(function() {
        global $gdic;
        $box = $gdic['spec'];
        $this->adapter = $box->get('source.database.mysql');
    });

    describe("sources", function() {

        it("show sources", function() {
            $schema = new Schema([
                'name' => 'table1',
                'adapter' =>  $this->adapter
            ]);
            $schema->create();
            $sources = $this->adapter->sources();
            print_r($sources);
            //print_r($this->source->describe());

            // $statement = $this->source->execute("SELECT * FROM authors LEFT JOIN tags ON authors.id = tags.author_id");
            // print_r($statement->fetchAll(PDO::FETCH_NUM));


// "column_name" AS "field", "data_type" AS "type", ';
// $sql .= '"is_nullable" AS "null", "column_default" AS "default", ';
// $sql .= '"character_maximum_length" AS "char_length"

            //$statement = $this->source->execute("select * from INFORMATION_SCHEMA.COLUMNS where table_name = 'authors'");

            // $statement = $this->source->execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'chaos_test'");
            // print_r($statement->fetchAll(PDO::FETCH_NUM));

            // print_r($this->source->sources());
        });

    });

});

?>