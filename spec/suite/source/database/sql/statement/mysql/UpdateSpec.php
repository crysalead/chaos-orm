<?php
namespace chaos\spec\suite\source\database\sql\statement;

describe("Update", function() {

    beforeEach(function() {
        $this->adapter = box('chaos.spec')->get('source.database.mysql');
        $this->update = $this->adapter->dialect()->statement('update');
    });

    describe("->lowPriority()", function() {

        it("sets the `LOW_PRIORITY` flag", function() {

            $this->update
                ->lowPriority()
                ->table('table')
                ->values(['field' => 'value']);

            expect($this->update->toString())->toBe('UPDATE LOW_PRIORITY `table` SET `field` = \'value\'');

        });

    });

    describe("->ignore()", function() {

        it("sets the `IGNORE` flag", function() {

            $this->update
                ->ignore()
                ->table('table')
                ->values(['field' => 'value']);

            expect($this->update->toString())->toBe('UPDATE IGNORE `table` SET `field` = \'value\'');

        });

    });

});
