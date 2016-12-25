<?php
namespace Chaos\ORM\Spec\Suite;

use Chaos\ORM\Buffer;

describe("Buffer", function() {

    beforeEach(function() {
        $this->collection = [
            ['id' => '1', 'name' => 'Foo Gallery'],
            ['id' => '2', 'name' => 'Bar Gallery']
        ];
    });

    describe("->all()", function() {

        it("retuns all records", function() {

            $buffer = new Buffer($this->collection);
            expect($buffer->all())->toEqual($this->collection);

        });

    });

    describe("->get()", function() {

        it("retuns all records", function() {

            $buffer = new Buffer($this->collection);
            expect($buffer->get())->toEqual($this->collection);

        });

    });

    describe("->first()", function() {

        it("finds the first record", function() {

            $buffer = new Buffer($this->collection);
            expect($buffer->first())->toEqual(reset($this->collection));

        });

    });

    describe("->getIterator()", function() {

        it("implements `IteratorAggregate`", function() {

            $buffer = new Buffer($this->collection);

            $result = [];

            foreach ($buffer as $record) {
                $result[] = $record;
            }

            expect($result)->toEqual($this->collection);

        });

    });

    describe("->count()", function() {

        it("returns the records count", function() {

            $buffer = new Buffer($this->collection);
            expect($buffer->count())->toBe(2);

        });

    });

});
