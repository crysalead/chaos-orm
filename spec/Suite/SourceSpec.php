<?php
namespace Chaos\Database\Spec\Suite;

use DateTime;
use DateTimeZone;
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

    describe("->convert()", function() {

        it("formats according default `'datasource'` handlers", function() {

            expect($this->source->convert('datasource', 'id', 123))->toBe('123');
            expect($this->source->convert('datasource', 'serial', 123))->toBe('123');
            expect($this->source->convert('datasource', 'integer', 123))->toBe('123');
            expect($this->source->convert('datasource', 'float', 12.3))->toBe('12.3');
            expect($this->source->convert('datasource', 'decimal', 12.3))->toBe('12.3');
            expect($this->source->convert('datasource', 'date', '2014-11-21'))->toBe('2014-11-21');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->source->convert('datasource', 'datetime', $datetime))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('datasource', 'datetime', '2014-11-21 10:20:45'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('datasource', 'boolean', true))->toBe('1');
            expect($this->source->convert('datasource', 'boolean', false))->toBe('0');
            expect($this->source->convert('datasource', 'null', null))->toBe('');
            expect($this->source->convert('datasource', 'string', 'abc'))->toBe('abc');
            expect($this->source->convert('datasource', '_default_', 123))->toBe('123');
            expect($this->source->convert('datasource', '_undefined_', 123))->toBe('123');

        });

        it("formats according default `'cast'` handlers", function() {

            expect($this->source->convert('cast', 'id', '123'))->toBe(123);
            expect($this->source->convert('cast', 'serial', '123'))->toBe(123);
            expect($this->source->convert('cast', 'integer', '123'))->toBe(123);
            expect($this->source->convert('cast', 'float', '12.3'))->toBe(12.3);
            expect($this->source->convert('cast', 'decimal', '12.3'))->toBe('12.30');
            $date = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 00:00:00');
            expect($this->source->convert('cast', 'date', $date))->toEqual($date);
            expect($this->source->convert('cast', 'date', '2014-11-21'))->toEqual($date);
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'datetime', $datetime)->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'datetime', '2014-11-21 10:20:45')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'datetime', '1416565245')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'boolean', 1))->toBe(true);
            expect($this->source->convert('cast', 'boolean', 0))->toBe(false);
            expect($this->source->convert('cast', 'null', ''))->toBe(null);
            expect($this->source->convert('cast', 'string', 'abc'))->toBe('abc');
            expect($this->source->convert('cast', '_default_', 'abc'))->toBe('abc');
            expect($this->source->convert('cast', '_undefined_', 'abc'))->toBe('abc');

        });

    });

});
