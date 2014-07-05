<?php
namespace spec\chaos\source\database\sql;

use chaos\source\database\sql\Sql;
use chaos\source\database\sql\SqlException;
use kahlan\plugin\Stub;

describe("Sql", function() {

	beforeEach(function() {
		$this->adapter = new Sql();
	});

	describe("select", function() {

		it("generates a `SELECT` clause", function() {
			$part = $this->adapter->select();
			expect($part)->toBe('SELECT');
		});

	});

	describe("fields", function() {

		it("generates star", function() {
			$part = $this->adapter->fields();
			expect($part)->toBe('*');

			$part = $this->adapter->fields([]);
			expect($part)->toBe('*');

			$part = $this->adapter->fields('');
			expect($part)->toBe('*');
		});

		it("generates fields aliasing", function() {
			$fields = [
				'name' => ['field' => 'F1'],
				'name2' => ['field2' => 'F2']
			];
			$part = $this->adapter->fields($fields);
			expect($part)->toBe('"name"."field" AS "F1", "name2"."field2" AS "F2"');
		});

		context("with a table or an alias basement", function() {

			it("generates all fields from a table/alias", function() {
				$fields = ['name.*'];
				$part = $this->adapter->fields($fields);
				expect($part)->toBe('"name".*');
			});

			it("generates selects all fields from a table/alias using an array syntax", function() {
				$fields = ['name' => ['*']];
				$part = $this->adapter->fields($fields);
				expect($part)->toBe('"name".*');
			});

			it("generates fields from multiple tables/aliases", function() {
				$fields = [
					'name.*',
					'name2.field'
				];
				$part = $this->adapter->fields($fields);
				expect($part)->toBe('"name".*, "name2"."field"');
			});

			it("generates fields from multiple tables/aliases with an array syntax", function() {
				$fields = [
					'name' => ['*'],
					'name2' => ['field']
				];
				$part = $this->adapter->fields($fields);
				expect($part)->toBe('"name".*, "name2"."field"');
			});
		});

	});

	describe("from", function() {

		it("throws an exeception if no source is defined", function() {
			$closure = function() {
				$part = $this->adapter->from();
			};
			expect($closure)->toThrow(new SqlException("A `FROM` statement require at least one table."));
		});

		it("generates a `FORM` statement", function() {
			$part = $this->adapter->from('table');
			expect($part)->toBe('FROM "table"');
		});

		it("generates a cross product", function() {
			$part = $this->adapter->from(['table', 'table2']);
			expect($part)->toBe('FROM "table", "table2"');
		});

		it("generates aliases", function() {
			$part = $this->adapter->from(['table' => 'T1', 'table2' => 'T2']);
			expect($part)->toBe('FROM "table" AS "T1", "table2" AS "T2"');
		});

	});

	describe("conditions", function() {

		it("generates a equal expression", function() {
			$part = $this->adapter->conditions([
				'field1' => 'value',
				'field2' => 10
			]);
			expect($part)->toBe('"field1" = \'value\' AND "field2" = 10');
		});

		it("generates a equal expression between fields", function() {
			$part = $this->adapter->conditions([
				['=' => [
					[':key' => 'field1'],
					[':key' => 'field2']
				]],
				['=' => [
					[':key' => 'field3'],
					[':key' => 'field4']
				]]
			]);
			expect($part)->toBe('"field1" = "field2" AND "field3" = "field4"');
		});

		it("generates a comparison expression", function() {
			$part = $this->adapter->conditions([
				['>' => [[':key' => 'field'], 10]],
				['<=' => [[':key' => 'field'], 15]]
			]);
			expect($part)->toBe('"field" > 10 AND "field" <= 15');
		});

		it("generates a BETWEEN/NOT BETWEEN expression", function() {
			$part = $this->adapter->conditions([
				':between' => [[':key' => 'score'], 90, 100]
			]);
			expect($part)->toBe('"score" BETWEEN 90 AND 100');

			$part = $this->adapter->conditions([
				':not between' => [[':key' => 'score'], 90, 100]
			]);
			expect($part)->toBe('"score" NOT BETWEEN 90 AND 100');
		});

		it("generates a subquery IN expression", function() {
			$part = $this->adapter->conditions([
				':in' => [[':key' => 'score'], 1, 2, 3, 4, 5]
			]);
			expect($part)->toBe('"score" IN (1, 2, 3, 4, 5)');
		});

		it("generates a subquery NOT IN expression", function() {
			$part = $this->adapter->conditions([
				':not in' => [[':key' => 'score'], 1, 2, 3, 4, 5]
			]);
			expect($part)->toBe('"score" NOT IN (1, 2, 3, 4, 5)');
		});

		it("generates a subquery ANY expression", function() {
			$part = $this->adapter->conditions([
				':any' => [
					[':key' => 'score'],
					[':raw' => 'SELECT "s1" FROM "t1"']
				]
			]);
			expect($part)->toBe('"score" ANY (SELECT "s1" FROM "t1")');
		});

		it("generates a subquery ANY expression with a subquery instance", function() {
			$subquery = $this->adapter->statement('select');
			$subquery->fields('s1')->from('t1');

			$part = $this->adapter->conditions([
				':any' => [
					[':key' => 'score'],
					[':raw' => $subquery]
				]
			]);
			expect($part)->toBe('"score" ANY (SELECT "s1" FROM "t1")');
		});

		it("generates an comparison expression with arrays", function() {
			$part = $this->adapter->conditions([
				'<>' => [
					[':value' => [1 ,2, 3]],
					[':value' => [1, 2, 3]]
				]
			]);
			expect($part)->toBe('ARRAY[1, 2, 3] <> ARRAY[1, 2, 3]');
		});

		context("with the alternative syntax", function() {
			it("generates a BETWEEN/NOT BETWEEN expression", function() {
				$part = $this->adapter->conditions([
					'score' => [':between' => [90, 100]]
				]);
				expect($part)->toBe('"score" BETWEEN 90 AND 100');
			});
		});

	});

});

?>