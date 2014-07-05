<?php
namespace spec\chaos\source\database\sql\statement;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Select", function() {

	beforeEach(function() {
		$sql = new Sql();
		$this->select = $sql->statement('select');
	});

	describe("select", function() {

		it("generates a SELECT statement", function() {
			$this->select->from('table');
			expect((string) $this->select)->toBe('SELECT * FROM "table"');
		});

	});

	describe("_toString" , function() {

		it("casts object to string query", function() {
			$this->select->from('table');
			$query = 'SELECT * FROM "table"';
			expect($this->select)->not->toBe($query);
			expect((string) $this->select)->toBe($query);
			expect("{$this->select}")->toBe($query);
		});

	});

});

?>