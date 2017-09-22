<?php
namespace Chaos\ORM\Database\Spec\Suite;

use InvalidArgumentException;
use DateTime;
use DateTimeZone;
use Chaos\ORM\Source;
use Chaos\ORM\Document;
use Chaos\ORM\Collection\Collection;

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
            expect($this->source->convert('datasource', 'object', new Document()))->toBe([]);
            expect($this->source->convert('datasource', 'object', new Collection()))->toBe([]);
            expect($this->source->convert('datasource', '_default_', 123))->toBe('123');
            expect($this->source->convert('datasource', '_undefined_', 123))->toBe('123');
            expect($this->source->convert('datasource', 'json', [1,2]))->toBe('[1,2]');

        });

        it("doesn't format `null` value on export", function() {

            expect($this->source->convert('datasource', 'integer', null))->toBe('');
            expect($this->source->convert('datasource', 'float', null))->toBe('');
            expect($this->source->convert('datasource', 'decimal', null))->toBe('');
            expect($this->source->convert('datasource', 'date', null))->toEqual('');
            expect($this->source->convert('datasource', 'datetime', null))->toBe('');
            expect($this->source->convert('datasource', 'boolean', null))->toBe('');
            expect($this->source->convert('datasource', 'null', null))->toBe('');
            expect($this->source->convert('datasource', 'string', null))->toBe('');
            expect($this->source->convert('datasource', '_default_', null))->toBe('');
            expect($this->source->convert('datasource', '_undefined_', null))->toBe('');

        });

        it("throws an exception when exporting an invalid date", function() {

            $closure = function() {
                $this->source->convert('datasource', 'date', '0000-00-00');
            };
            expect($closure)->toThrow(new InvalidArgumentException("Invalid date `0000-00-00`, can't be parsed."));

            $closure = function() {
                $this->source->convert('datasource', 'date', '2016-25-15');
            };
            expect($closure)->toThrow(new InvalidArgumentException("Invalid date `2016-25-15`, can't be parsed."));

            $closure = function() {
                $this->source->convert('datasource', 'datetime', '2016-12-15 80:90:00');
            };
            expect($closure)->toThrow(new InvalidArgumentException("Invalid date `2016-12-15 80:90:00`, can't be parsed."));

            $closure = function() {
                $this->source->convert('datasource', 'datetime', '0000-00-00 00:00:00');
            };
            expect($closure)->toThrow(new InvalidArgumentException("Invalid date `0000-00-00 00:00:00`, can't be parsed."));

        });

        it("formats according default `'cast'` handlers", function() {

            expect($this->source->convert('cast', 'integer', '123'))->toBe(123);
            expect($this->source->convert('cast', 'float', '12.3'))->toBe(12.3);
            expect($this->source->convert('cast', 'decimal', '12.3'))->toBe('12.30');
            $date = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 00:00:00', new DateTimeZone('UTC'));
            expect($this->source->convert('cast', 'date', $date))->toEqual($date);
            expect($this->source->convert('cast', 'date', '2014-11-21'))->toEqual($date);
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45', new DateTimeZone('UTC'));
            expect($this->source->convert('cast', 'datetime', $datetime)->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'datetime', '2014-11-21 10:20:45')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'datetime', '1416565245')->format('Y-m-d H:i:s'))->toBe('2014-11-21 10:20:45');
            expect($this->source->convert('cast', 'boolean', 1))->toBe(true);
            expect($this->source->convert('cast', 'boolean', 0))->toBe(false);
            expect($this->source->convert('cast', 'null', ''))->toBe(null);
            expect($this->source->convert('cast', 'string', 'abc'))->toBe('abc');
            expect($this->source->convert('cast', '_default_', 'abc'))->toBe('abc');
            expect($this->source->convert('cast', '_undefined_', 'abc'))->toBe('abc');
            expect($this->source->convert('cast', 'json', '[1,2]'))->toBe([1,2]);

        });

        it("doesn't format `null` value on import", function() {

            expect($this->source->convert('cast', 'integer', null))->toBe(null);
            expect($this->source->convert('cast', 'float', null))->toBe(null);
            expect($this->source->convert('cast', 'decimal', null))->toBe(null);
            expect($this->source->convert('cast', 'date', null))->toEqual(null);
            expect($this->source->convert('cast', 'datetime', null))->toBe(null);
            expect($this->source->convert('cast', 'boolean', null))->toBe(null);
            expect($this->source->convert('cast', 'null', null))->toBe(null);
            expect($this->source->convert('cast', 'string', null))->toBe(null);
            expect($this->source->convert('cast', '_default_', null))->toBe(null);
            expect($this->source->convert('cast', '_undefined_', null))->toBe(null);

        });

        it("format invalid date as `null` on import", function() {

            expect($this->source->convert('cast', 'date', '0000-00-00'))->toBe(null);
            expect($this->source->convert('cast', 'date', '2016-25-15'))->toBe(null);
            expect($this->source->convert('cast', 'datetime', '2016-12-15 80:90:00'))->toBe(null);
            expect($this->source->convert('cast', 'datetime', '0000-00-00 00:00:00'))->toBe(null);

        });

    });

});
