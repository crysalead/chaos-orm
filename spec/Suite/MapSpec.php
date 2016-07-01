<?php
namespace Chaos\Spec\Suite;

use Chaos\ChaosException;
use stdClass;
use Chaos\Map;

describe("Map", function() {

  describe("->get()/->set()", function() {

    it("sets values", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->get($instance))->toBe('Hello');

    });

    it("throws an error for unexisting id", function() {

      $closure = function() {
        $map = new Map();
        $map->get(new stdClass());
      };

      expect($closure)->toThrow(new ChaosException("No collected data associated to the key."));

    });

  });

  describe("->has()", function() {

    it("returns `true` if a element has been setted", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->has($instance))->toBe(true);

    });


    it("returns false if a element doesn't exist", function() {

      $map = new Map();
      expect($map->has(new stdClass()))->toBe(false);

    });

  });

  describe("->remove()", function() {

    it("removes items", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->remove($instance))->toBe($map);
      expect($map->has($instance))->toBe(false);

    });

  });

  describe("->count()", function() {

    it("removes items", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->count())->toBe(0);
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->count())->toBe(1);

    });

  });

});