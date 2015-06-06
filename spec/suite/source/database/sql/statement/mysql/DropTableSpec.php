<?php
namespace chaos\spec\suite\source\database\sql\statement\mysql;

use chaos\SourceException;
use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("DropTable", function() {

    beforeEach(function() {
        $this->adapter = box('chaos.spec')->get('source.database.mysql');
        $this->drop = $this->adapter->sql()->statement('drop table');
    });

    describe("->table()", function() {

        it("generates a DROP TABLE statement", function() {

            $this->drop->table('table1');

            $expected = 'DROP TABLE `table1`';
            expect($this->drop->toString())->toBe($expected);

        });

        it("generates a soft DROP TABLE statement", function() {

            $this->drop->table('table1')
                ->ifExists();

            $expected  = 'DROP TABLE IF EXISTS `table1`';
            expect($this->drop->toString())->toBe($expected);

        });

        it("generates a DROP TABLE with CASCADE enabled", function() {

            $this->drop->table('table1')
                ->cascade(true);

            $expected  = 'DROP TABLE `table1` CASCADE';
            expect($this->drop->toString())->toBe($expected);

        });

        it("generates a DROP TABLE with RESTRICT enabled", function() {

            $this->drop->table('table1')
                ->restrict(true);

            $expected  = 'DROP TABLE `table1` RESTRICT';
            expect($this->drop->toString())->toBe($expected);

        });

    });

});
