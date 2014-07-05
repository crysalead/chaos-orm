<?php
namespace spec\chaos;

use PDO;
use chaos\source\database\adapter\MySql;
use chaos\Query;
use kahlan\plugin\Stub;

describe("Query", function() {

	before(function() {
		$this->source = new MySql([
			'database' => 'chaos_test'
		]);
	});

	describe("select", function() {

		it("", function() {
			$statement = $this->source->execute("SELECT * FROM authors LEFT JOIN tags ON authors.id = tags.author_id");
			print_r($statement->fetchAll(PDO::FETCH_NUM));


// "column_name" AS "field", "data_type" AS "type", ';
// $sql .= '"is_nullable" AS "null", "column_default" AS "default", ';
// $sql .= '"character_maximum_length" AS "char_length"

			//$statement = $this->source->execute("select * from INFORMATION_SCHEMA.COLUMNS where table_name = 'authors'");

			$statement = $this->source->execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'chaos_test'");
			print_r($statement->fetchAll(PDO::FETCH_NUM));

			print_r($this->source->sources());
		});

	});

});

?>