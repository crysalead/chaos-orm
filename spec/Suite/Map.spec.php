<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use Chaos\ORM\ORMException;
use Chaos\ORM\Map;

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

      expect($closure)->toThrow(new ORMException("No collected data associated to the key."));

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

  describe("->delete()", function() {

    it("deletes items", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->delete($instance))->toBe($map);
      expect($map->has($instance))->toBe(false);

    });

  });

  describe("->count()", function() {

    it("counts items", function() {

      $map = new Map();
      $instance = new stdClass();
      expect($map->count())->toBe(0);
      expect($map->set($instance, 'Hello'))->toBe($map);
      expect($map->count())->toBe(1);

    });

  });

});