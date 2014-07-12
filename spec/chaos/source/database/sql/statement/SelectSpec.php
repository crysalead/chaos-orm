<?php
namespace spec\chaos\source\database\sql\statement;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;
use chaos\SourceException;

describe("Select", function() {

    beforeEach(function() {
        $sql = new Sql();
        $this->select = $sql->statement('select');
    });

    describe("select", function() {

        it("generates a SELECT statement", function() {
            $this->select->from('table');
            expect($this->select->toString())->toBe('SELECT * FROM "table"');
        });
    });

    describe("from", function() {

        it("throws an exeception if no source is defined", function() {
            $closure = function() {
                $this->select->toString();
            };
            expect($closure)->toThrow(new SourceException("Invalid SELECT statement missing FORM clause."));
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