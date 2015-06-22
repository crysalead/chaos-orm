<?php
namespace chaos\spec\suite\source\database\sql\statement;

describe("Insert", function() {

    beforeEach(function() {
        $this->adapter = box('chaos.spec')->get('source.database.mysql');
        $this->insert = $this->adapter->dialect()->statement('insert');
    });

    describe("->lowPriority()", function() {

        it("sets the `LOW_PRIORITY` flag", function() {

            $this->insert
                ->lowPriority()
                ->into('table')
                ->values(['field' => 'value']);

            expect($this->insert->toString())->toBe('INSERT LOW_PRIORITY INTO `table` (`field`) VALUES (\'value\')');

        });

    });

    describe("->highPriority()", function() {

        it("sets the `HIGH_PRIORITY` flag", function() {

            $this->insert
                ->highPriority()
                ->into('table')
                ->values(['field' => 'value']);

            expect($this->insert->toString())->toBe('INSERT HIGH_PRIORITY INTO `table` (`field`) VALUES (\'value\')');

        });

    });


    describe("->ignore()", function() {

        it("sets the `IGNORE` flag", function() {

            $this->insert
                ->ignore()
                ->into('table')
                ->values(['field' => 'value']);

            expect($this->insert->toString())->toBe('INSERT IGNORE INTO `table` (`field`) VALUES (\'value\')');

        });

    });

    describe("->delayed()", function() {

        it("sets the `DELAYED` flag", function() {

            $this->insert
                ->delayed()
                ->into('table')
                ->values(['field' => 'value']);

            expect($this->insert->toString())->toBe('INSERT DELAYED INTO `table` (`field`) VALUES (\'value\')');

        });

    });

});
