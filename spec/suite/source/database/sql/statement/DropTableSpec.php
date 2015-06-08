<?php
namespace chaos\spec\suite\source\database\sql\statement\mysql;

use chaos\SourceException;
use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("DropTable", function() {

    beforeEach(function() {
        $this->sql = new Sql();
        $this->drop = $this->sql->statement('drop table');
    });

    describe("->table()", function() {

        it("sets the `TABLE` clause", function() {

            $this->drop->table('table1');

            $expected = 'DROP TABLE "table1"';
            expect($this->drop->toString())->toBe($expected);

        });

    });

    describe("->ifExists()", function() {

        it("sets the `IF EXISTS` flag", function() {

            $this->drop->table('table1')
                ->ifExists();

            $expected  = 'DROP TABLE IF EXISTS "table1"';
            expect($this->drop->toString())->toBe($expected);

        });

    });

    describe("->cascade()", function() {

        it("sets the `CASCADE` flag", function() {

            $this->drop->table('table1')
                ->cascade();

            $expected  = 'DROP TABLE "table1" CASCADE';
            expect($this->drop->toString())->toBe($expected);

        });

    });

    describe("->restrict()", function() {

        it("sets the `RESTRICT` flag", function() {

            $this->drop->table('table1')
                ->restrict();

            $expected  = 'DROP TABLE "table1" RESTRICT';
            expect($this->drop->toString())->toBe($expected);

        });

    });

});
