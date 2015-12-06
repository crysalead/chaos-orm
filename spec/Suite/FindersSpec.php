<?php
namespace Chaos\Spec\Suite;

use Chaos\ChaosException;
use Chaos\Finders;

use Kahlan\Plugin\Stub;

describe("Finders", function() {

    beforeEach(function() {
        $this->finders = new Finders();
    });

    describe("->get()/->set()", function() {

        it("gets/sets a finder", function() {

            $closure = function() {};

            expect($this->finders->set('myfinder', $closure))->toBe($this->finders);
            expect($this->finders->get('myfinder'))->toBe($closure);

        });

    });

    describe("->exists()", function() {

        it("checks if a finder exists", function() {

            $closure = function() {};

            expect($this->finders->exists('myfinder'))->toBe(false);
            $this->finders->set('myfinder', $closure);
            expect($this->finders->exists('myfinder'))->toBe(true);

        });

    });

    describe("->_call()", function() {

        it("calls a finder", function() {

            $closure = Stub::create();
            $this->finders->set('myfinder', $closure);

            expect($closure)->toReceive('__invoke')->with('a', 'b', 'c');

            $this->finders->myfinder('a', 'b', 'c');

        });

        it("throws an exception if no finder exists", function() {

            $closure = function() {
                $this->finders->myfinder('a', 'b', 'c');
            };

            expect($closure)->toThrow(new ChaosException("Unexisting finder `'myfinder'`."));

        });

    });

    describe("->remove()", function() {

        it("removes a finder", function() {

            $closure = function() {};

            $this->finders->set('myfinder', $closure);
            expect($this->finders->exists('myfinder'))->toBe(true);
            $this->finders->remove('myfinder');
            expect($this->finders->exists('myfinder'))->toBe(false);

        });

    });

    describe("->clear()", function() {

        it("removes all defined finders", function() {

            $closure = function() {};
            $this->finders->set('myfinder', $closure);
            expect($this->finders->exists('myfinder'))->toBe(true);
            $this->finders->clear();
            expect($this->finders->exists('myfinder'))->toBe(false);

        });

    });
});