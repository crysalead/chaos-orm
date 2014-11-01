<?php
namespace spec\chaos;

use PDO;
use chaos\source\database\adapter\MySql;
use chaos\source\Schema;

use kahlan\plugin\Stub;

describe("MySql", function() {

    before(function() {
        $this->adapter = box('chaos.spec')->get('source.database.postgresql');
    });

    describe("->sources()", function() {

        it("show sources", function() {
            // $schema = new Schema([
            //     'name' => 'table1',
            //     'adapter' =>  $this->adapter,
            //     'fields' => [
            //         'id' => ['id']
            //     ]
            // ]);
            // $schema->create();
            // $sources = $this->adapter->sources();
            // print_r($sources);

            //print_r($this->adapter->describe());

            $statement = $this->adapter->execute("SELECT row_to_json(aa) FROM aa");
            //print_r($statement->fetchAll(PDO::FETCH_ASSOC));

            // $statement = $this->adapter->execute("SELECT * FROM authors LEFT JOIN tags ON authors.id = tags.author_id");
            // print_r($statement->fetchAll(PDO::FETCH_NUM));


// "column_name" AS "field", "data_type" AS "type", ';
// $sql .= '"is_nullable" AS "null", "column_default" AS "default", ';
// $sql .= '"character_maximum_length" AS "char_length"

            $statement = $this->adapter->execute("select * from INFORMATION_SCHEMA.COLUMNS where table_name = 'bb'");

            // $statement = $this->adapter->execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'chaos_test'");
            //print_r($statement->fetchAll(PDO::FETCH_ASSOC));

            // print_r($this->adapter->sources());
        });

    });

});

?>