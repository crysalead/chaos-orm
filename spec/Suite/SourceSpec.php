<?php
namespace Chaos\Database\Spec\Suite;

use DateTime;
use Chaos\Source;

describe("Source", function() {

    beforeEach(function() {

        $this->source = new Source();

    });

    describe("->formatter()", function() {

        it("gets/sets a formatter", function() {

            $handler = function() {};
            expect($this->source->formatter('custom', 'mytype', $handler))->toBe($this->source);
            expect($this->source->formatter('custom', 'mytype'))->toBe($handler);


        });

        it("returns the `'_default_'` handler if no handler found", function() {

            $dflt = function() {};
            $this->source->formatter('cast', '_default_', $dflt);
            expect($this->source->formatter('cast', 'mytype'))->toBe($dflt);

        });

    });

    describe("->formatter()", function() {

        it("gets/sets a formatter", function() {

            $handlers = [
                'cast' => [
                    'mytype' => function() {}
                ]
            ];

            $this->source->formatters($handlers);
            expect($this->source->formatters())->toBe($handlers);

        });

    });

    describe("->format()", function() {

        it("formats according default `'datasource'` handlers", function() {

            expect($this->source->format('datasource', 'id', 123))->toBe('123');
            expect($this->source->format('datasource', 'serial', 123))->toBe('123');
            expect($this->source->format('datasource', 'integer', 123))->toBe('123');
            expect($this->source->format('datasource', 'float', 12.3))->toBe('12.3');
            expect($this->source->format('datasource', 'decimal', 12.3))->toBe('12.3');
            expect($this->source->format('datasource', 'date', '2014-11-21'))->toBe('2014-11-21');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->source->format('datasource', 'datetime', $datetime))->toBe('2014-11-21 10:20:45');
            expect($this->source->format('datasource', 'datetime', '2014-11-21 10:20:45'))->toBe('2014-11-21 10:20:45');
            expect($this->source->format('datasource', 'boolean', true))->toBe('1');
            expect($this->source->format('datasource', 'null', null))->toBe('');
            expect($this->source->format('datasource', 'string', 'abc'))->toBe('abc');
            expect($this->source->format('datasource', '_default_', 123))->toBe('123');
            expect($this->source->format('datasource', '_undefined_', 123))->toBe('123');

        });

        it("formats according default `'cast'` handlers", function() {

            expect($this->source->format('cast', 'id', '123'))->toBe(123);
            expect($this->source->format('cast', 'serial', '123'))->toBe(123);
            expect($this->source->format('cast', 'integer', '123'))->toBe(123);
            expect($this->source->format('cast', 'float', '12.3'))->toBe(12.3);
            expect($this->source->format('cast', 'decimal', '12.3'))->toBe(12.3);
            $date = DateTime::createFromFormat('Y-m-d', '2014-11-21');
            expect($this->source->format('cast', 'date', $date)->format('Y-m-d'))->toBe('2014-11-21');
            expect($this->source->format('cast', 'date', '2014-11-21')->format('Y-m-d'))->toBe('2014-11-21');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->source->format('cast', 'datetime', $datetime)->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->format('cast', 'datetime', '2014-11-21 10:20:45')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->format('cast', 'datetime', '1416565245')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->format('cast', 'boolean', 'TRUE'))->toBe(true);
            expect($this->source->format('cast', 'null', 'NULL'))->toBe(null);
            expect($this->source->format('cast', 'string', 'abc'))->toBe('abc');
            expect($this->source->format('cast', '_default_', 'abc'))->toBe('abc');
            expect($this->source->format('cast', '_undefined_', 'abc'))->toBe('abc');

        });

    });

});
