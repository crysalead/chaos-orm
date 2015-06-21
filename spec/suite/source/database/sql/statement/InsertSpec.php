<?php
namespace chaos\spec\suite\source\database\sql\statement;

use chaos\SourceException;
use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Insert", function() {

    beforeEach(function() {
        $this->sql = new Sql();
        $this->insert = $this->sql->statement('insert');
    });

    describe("->into()", function() {

        it("sets the `INTO` clause", function() {

            $this->insert->into('table')->values([
                'field1' => 'value1',
                'field2' => 'value2'
            ]);
            expect($this->insert->toString())->toBe('INSERT INTO "table" ("field1", "field2") VALUES (\'value1\', \'value2\')');

        });

    });

    describe("->__toString()" , function() {

        it("casts object to string query", function() {

            $this->insert->into('table')->values(['field' => 'value']);;
            $query = 'INSERT INTO "table" ("field") VALUES (\'value\')';
            expect($this->insert)->not->toBe($query);
            expect((string) $this->insert)->toBe($query);
            expect("{$this->insert}")->toBe($query);

        });

    });

});