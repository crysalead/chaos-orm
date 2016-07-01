<?php
namespace Chaos\Spec\Suite;

use Chaos\ChaosException;
use Chaos\Collector;

describe("Collector", function() {

  describe("->get()/->set()", function() {

    it("sets values", function() {

      $collector = new Collector();
      expect($collector->set(123, 'Hello'))->toBe($collector);
      expect($collector->get(123))->toBe('Hello');

    });

    it("throws an error for unexisting id", function() {

      $closure = function() {
        $collector = new Collector();
        $collector->get(123);
      };

      expect($closure)->toThrow(new ChaosException("No collected data with UUID `'123'` in this collector."));

    });

  });

  describe("->has()", function() {

    it("returns `true` if a element has been setted", function() {

      $collector = new Collector();
      expect($collector->set(123, 'Hello'))->toBe($collector);
      expect($collector->has(123))->toBe(true);

    });


    it("returns false if a element doesn't exist", function() {

      $collector = new Collector();
      expect($collector->has(123))->toBe(false);

    });

  });

  describe("->remove()", function() {

    it("removes items", function() {

      $collector = new Collector();
      expect($collector->set(123, 'Hello'))->toBe($collector);
      expect($collector->remove(123))->toBe($collector);
      expect($collector->has(123))->toBe(false);

    });

  });

});