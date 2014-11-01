<?php
namespace spec\chaos\source\database\sql\statement;

use chaos\SourceException;
use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Select", function() {

    beforeEach(function() {
        $this->sql = new Sql();
        $this->select =  $this->sql->statement('select');
    });

    describe("->select()", function() {

        it("generates a SELECT statement", function() {
            $this->select->from('table');
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

    });

    describe("->from()", function() {

        it("throws an exeception if the table source is empty", function() {
            $closure = function() {
                $this->select->from('');
            };
            expect($closure)->toThrow(new SourceException("A `FROM` statement require at least one non empty table."));
        });

        it("generates a `FORM` statement", function() {
            $this->select->from('table');
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

        it("generates a cross product", function() {
            $this->select->from(['table', 'table2']);
            expect($this->select->toString())->toBe('SELECT * FROM "table", "table2"');
        });

        it("generates aliases", function() {
            $this->select->from(['table' => 'T1', 'table2' => 'T2']);
            expect($this->select->toString())->toBe('SELECT * FROM "table" AS "T1", "table2" AS "T2"');
        });

    });

    describe("->join()", function() {

        it("generates a `LEFT JOIN` statement", function() {
            $this->select->from('table')->join('table2');
            expect($this->select->toString())->toBe('SELECT * FROM "table" LEFT JOIN "table2"');
        });

        it("generates a `LEFT JOIN` statement with an alias", function() {
            $this->select->from('table')->join(['table2' => 't2']);
            expect($this->select->toString())->toBe('SELECT * FROM "table" LEFT JOIN "table2" AS "t2"');
        });

        it("generates a `RIGHT JOIN` statement with an alias", function() {
            $this->select->from('table')->join(['table2' => 't2'], [], 'right');
            expect($this->select->toString())->toBe('SELECT * FROM "table" RIGHT JOIN "table2" AS "t2"');
        });

        it("generates a `LEFT JOIN` statement using a subquery", function() {
            $subquery = $this->sql->statement('select');
            $subquery->from('table2')->alias('t2');

            $this->select->from('table')->join($subquery);
            expect($this->select->toString())->toBe('SELECT * FROM "table" LEFT JOIN (SELECT * FROM "table2") AS "t2"');
        });

        it("generates a `LEFT JOIN` statement with an `ON` statement", function() {
            $on = ['=' => [
               [':key' => 't.table2_id'],
               [':key' => 't2.id']
            ]];
            $this->select->from(['table' => 't'])->join(['table2' => 't2'], $on);
            expect($this->select->toString())->toBe('SELECT * FROM "table" AS "t" LEFT JOIN "table2" AS "t2" ON "t"."table2_id" = "t2"."id"');
        });

        it("doesn't generate any `JOIN` with when an empty parameter is passed", function() {
            $this->select->from('table')->join();
            $this->select->from('table')->join(null);
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

    });

    describe("->where()", function() {

        it("generates a `WHERE` statement", function() {
            $this->select->from('table')->where([true]);
            expect($this->select->toString())->toBe('SELECT * FROM "table" WHERE TRUE');
        });

    });

    describe("->group()", function() {

        it("generates a `GROUP BY` statement", function() {
            $this->select->from('table')->group('field');
            expect($this->select->toString())->toBe('SELECT * FROM "table" GROUP BY "field"');
        });

        it("generates a `GROUP BY` statement with multiple fields", function() {
            $this->select->from('table')->group(['field1', 'field2']);
            expect($this->select->toString())->toBe('SELECT * FROM "table" GROUP BY "field1", "field2"');
        });

        it("doesn't generate an `GROUP BY` with an invalid field names", function() {
            $this->select->from('table')->group();
            $this->select->from('table')->group('');
            $this->select->from('table')->group([]);
            $this->select->from('table')->group(null);
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

    });

    describe("->having()", function() {

        it("generates a `GROUP` statement", function() {
            $this->select->from('table')->group('field')->having([true]);
            expect($this->select->toString())->toBe('SELECT * FROM "table" GROUP BY "field" HAVING TRUE');
        });

    });

    describe("->order()", function() {

        it("generates a `ORDER BY` statement", function() {
            $this->select->from('table')->order('field');
            expect($this->select->toString())->toBe('SELECT * FROM "table" ORDER BY "field" ASC');
        });

        it("generates a `ORDER BY` statement with a DESC direction", function() {
            $this->select->from('table')->order(['field' => 'DESC']);
            expect($this->select->toString())->toBe('SELECT * FROM "table" ORDER BY "field" DESC');
        });

        it("generates a `ORDER BY` statement with a DESC direction (compatibility syntax)", function() {
            $this->select->from('table')->order('field DESC');
            expect($this->select->toString())->toBe('SELECT * FROM "table" ORDER BY "field" DESC');
        });

        it("generates a `GROUP BY` statement with multiple fields", function() {
            $this->select->from('table')->order(['field1' => 'ASC', 'field2' => 'DESC']);
            expect($this->select->toString())->toBe('SELECT * FROM "table" ORDER BY "field1" ASC, "field2" DESC');
        });

        it("doesn't generate an `ORDER BY` with an invalid field names", function() {
            $this->select->from('table')->order();
            $this->select->from('table')->order('');
            $this->select->from('table')->order([]);
            $this->select->from('table')->order(null);
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

    });

    describe("->limit()", function() {

        it("generates a `LIMIT` statement", function() {
            $this->select->from('table')->limit(50);
            expect($this->select->toString())->toBe('SELECT * FROM "table" LIMIT 50');
        });

        it("generates a `LIMIT` statement with a offset value", function() {
            $this->select->from('table')->limit(50, 10);
            expect($this->select->toString())->toBe('SELECT * FROM "table" LIMIT 50 OFFSET 10');
        });

        it("doesn't generate an `ORDER BY` with an invalid field names", function() {
            $this->select->from('table')->limit();
            $this->select->from('table')->limit(0, 0);
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });

    });

    describe("->alias()", function() {

        it("sets the alias", function() {
            $this->select->from('table2')->alias('t2');
            expect($this->select->toString())->toBe('(SELECT * FROM "table2") AS "t2"');
        });

        it("returns the alias", function() {
            $this->select->from('table2')->alias('t2');
            expect($this->select->alias())->toBe('t2');
        });

        it("clears the alias", function() {
            $this->select->from('table2')->alias('t2');
            expect($this->select->toString())->toBe('(SELECT * FROM "table2") AS "t2"');

            $this->select->alias(null);
            expect($this->select->toString())->toBe('SELECT * FROM "table2"');
        });

    });

    describe("->toString()" , function() {

        it("throws an exception if no source is defined", function() {
            $closure = function() {
                $this->select->toString();
            };
            expect($closure)->toThrow(new SourceException("Invalid SELECT statement missing FORM clause."));
        });

    });

    describe("->__toString()" , function() {

        it("casts object to string query", function() {
            $this->select->from('table');
            $query = 'SELECT * FROM "table"';
            expect($this->select)->not->toBe($query);
            expect((string) $this->select)->toBe($query);
            expect("{$this->select}")->toBe($query);
        });

    });

});
