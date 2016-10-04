<?php
namespace Chaos\Spec\Suite\Collection;

use InvalidArgumentException;
use Chaos\Model;
use Chaos\Schema;
use Chaos\Document;
use Chaos\Collection\Collection;

use Kahlan\Plugin\Double;

describe("Collection", function() {

    beforeEach(function() {
        $model = $this->model = Double::classname(['extends' => Model::class]);
        $model::definition()->locked(false);
    });

    describe("->__construct()", function() {

        it("loads the data", function() {

            $collection = new Collection(['data' => ['foo']]);
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

    });

    describe("->parents()", function() {

        it("gets the parents", function() {

            $parent = new Document();
            $collection = new Collection();
            $parent->value = $collection;
            expect($collection->parents()->has($parent))->toBe(true);
            expect($collection->parents()->get($parent))->toBe('value');

        });
    });

    describe("->removeParent()", function() {

        it("removes a parent", function() {

            $parent = new Document();
            $collection = new Collection();
            $parent->value = $collection;
            unset($parent->value);
            expect($collection->parents()->has($parent))->toBe(false);

        });

    });

    describe("->basePath()", function() {

        it("returns the root path", function() {

            $collection = new Collection(['basePath' => 'items']);
            expect($collection->basePath())->toBe('items');

        });

    });

    describe("->schema()", function() {

        it("returns the schema", function() {

            $schema = new Schema();
            $collection = new Collection(['schema' => $schema]);
            expect($collection->schema())->toBe($schema);

        });

    });

    describe("->meta()", function() {

        it("returns the meta attributes", function() {

            $collection = new Collection(['meta' => ['page' => 5, 'limit' => 10]]);
            expect($collection->meta())->toBe(['page' => 5, 'limit' => 10]);

        });

    });

    describe("->invoke()", function() {

        beforeEach(function() {
            $this->collection = new Collection();
            $class = Double::classname();

            allow($class)->toReceive('hello')->andRun(function() {
                return 'world';
            });

            for ($i = 0; $i < 5; $i++) {
                $this->collection[] = new $class();
            }
        });

        it("dispatches a method against all items in the collection", function() {

            foreach ($this->collection as $instance) {
                expect($instance)->toReceive('hello');
            }

            $result = $this->collection->invoke('hello');
            expect($result->data())->toBe(array_fill(0, 5, 'world'));

        });

    });

    describe("->each()", function() {

        it("applies a filter on a collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $filter = function($item) { return ++$item; };
            $result = $collection->each($filter);

            expect($result)->toBe($collection);
            expect($result->data())->toBe([2, 3, 4, 5, 6]);

        });

    });

    describe("->find()", function() {

        it("extracts items from a collection according a filter", function() {

            $collection = new Collection(['data' => array_merge(
                array_fill(0, 10, 1),
                array_fill(0, 10, 2)
            )]);

            $filter = function($item) { return $item === 1; };

            $result = $collection->find($filter);
            expect($result)->toBeAnInstanceOf(Collection::class);
            expect($result->data())->toBe(array_fill(0, 10, 1));

        });

    });

    describe("->map()", function() {

        it("applies a Closure to a copy of all data in the collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $filter = function($item) { return ++$item; };
            $result = $collection->map($filter);

            expect($result)->not->toBe($collection);
            expect($result->data())->toBe([2, 3, 4, 5, 6]);

        });

    });

    describe("->reduce()", function() {

        it("reduces a collection down to a single value", function() {

            $collection = new Collection(['data' => [1, 2, 3]]);
            $filter = function($memo, $item) { return $memo + $item; };

            expect($collection->reduce($filter, 0))->toBe(6);
            expect($collection->reduce($filter, 1))->toBe(7);

        });

    });

    describe("->slice()", function() {

        it("extracts a slice of items", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $result = $collection->slice(2, 2);

            expect($result)->not->toBe($collection);
            expect($result->data())->toBe([3, 4]);

        });

    });

    describe("->sort()", function() {

        it("sorts a collection", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            $result = $collection->sort();
            expect($result->data())->toBe([1, 2, 3, 4, 5]);

        });

        it("sorts a collection using a compare function", function() {

            $collection = new Collection(['data' => ['Alan', 'Dave', 'betsy', 'carl']]);
            $result = $collection->sort('strcasecmp');
            expect($result->data())->toBe(['Alan', 'betsy', 'carl', 'Dave']);

        });

        it("sorts a collection by keys", function() {

            $collection = new Collection(['data' => [5 => 6, 3 => 7, 4 => 8, 1 => 9, 2 => 10]]);
            $result = $collection->sort(null, 'ksort');
            expect($result->keys())->toBe([1, 2, 3, 4, 5]);

        });

        it("throws an exception if the sort function is not callable", function() {

            $closure = function() {
                $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
                $collection->sort(null, 'mysort');
            };

            expect($closure)->toThrow(new InvalidArgumentException("The passed parameter is not a valid sort function."));

        });

    });

    describe("->offsetExists()", function() {

        it("returns true if a element exist", function() {

            $collection = new Collection();
            $collection[] = 'foo';
            $collection[] = null;

            expect(isset($collection[0]))->toBe(true);
            expect(isset($collection[1]))->toBe(true);

        });

        it("returns false if a element doesn't exist", function() {

            $collection = new Collection();
            expect(isset($collection[0]))->toBe(false);

        });

        it("checks if a value has been setted using a dotted notation", function() {

            $model = $this->model;

            $collection = $model::create([
                ['name' => 'hello' ],
                ['name' => 'world', 'item' => ['a' => 'b']]
            ], ['type' => 'set']);

            expect(isset($collection['0.name']))->toBe(true);
            expect(isset($collection['1.name']))->toBe(true);
            expect(isset($collection['1.item.a']))->toBe(true);

        });

    });

    describe("->offsetSet/offsetGet()", function() {

        it("allows array access", function() {

            $collection = new Collection();
            $collection[] = 'foo';
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

        context("when a schema is defined", function() {

            it("autoboxes setted data", function() {

                $model = $this->model;

                $collection = new Collection([
                    'schema' => $model::definition()
                ]);

                $collection[] = [
                    'id'      => 1,
                    'title'   => 'first record',
                    'enabled' => 1,
                    'created' => time()
                ];
                $entity = $collection[0];
                expect($entity)->toBeAnInstanceOf($this->model);
                expect($entity->parents()->get($collection))->toBe(0);
                expect($entity->basePath())->toBe(null);

            });

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->data())->toBe([5, 1, 2]);

        });

        it("unsets items but keeps index", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->data())->toBe([5, 1, 2]);
            expect($collection->keys())->toBe([0, 3, 4]);

        });

        it("unsets items using a dotted notation", function() {

            $model = $this->model;

            $collection = $model::create([
              ['name' => 'hello'],
              ['name' => 'world']
            ], ['type' => 'set']);

            unset($collection['1.name']);

            expect(isset($collection['0.name']))->toBe(true);
            expect(isset($collection['1.name']))->toBe(false);

        });

        it("unsets all items in a foreach", function() {

            $data = ['Delete me', 'Delete me'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                unset($collection[$i]);
            }
            expect($collection->data())->toBe([]);

        });

        it("unsets last items in a foreach", function() {

            $data = ['Hello', 'Hello again!', 'Delete me'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }
            expect($collection->data())->toBe(['Hello', 'Hello again!']);

        });

        it("unsets first items in a foreach", function() {

            $data = ['Delete me', 'Hello', 'Hello again!'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }

            expect($collection->data())->toBe(['Hello', 'Hello again!']);

        });

        it("doesn't skip element in foreach", function() {

            $data = ['Delete me', 'Hello', 'Delete me', 'Hello again!'];
            $collection = new Collection(compact('data'));

            $loop = 0;
            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
                $loop++;
            }

            expect($loop)->toBe(4);

        });

    });

    describe("->has()", function() {

        it("delegates to `offsetExists`", function() {

            $collection = new Collection();
            expect($collection)->toReceive('offsetExists')->with(0);
            $collection->has(0);

        });

    });

    describe("->remove()", function() {

        it("delegates to `offsetUnset`", function() {

            $collection = new Collection();
            expect($collection)->toReceive('offsetUnset')->with(0);
            $collection->remove(0);

        });

    });

    describe("->keys()", function() {

        it("returns the item keys", function() {

            $collection = new Collection(['data' => [
                'one',
                'two',
                'three'
            ]]);
            expect($collection->keys())->toBe([0, 1, 2]);

        });

    });

    describe("->get()", function() {

        it("returns the plain data", function() {

            $data = [
                'one',
                'two',
                'three'
            ];
            $collection = new Collection(compact('data'));
            expect($collection->get())->toBe($data);

        });

    });

    describe("->key()", function() {

        it("returns current key", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $value = $collection->key();
            expect($value)->toBe(0);

        });

        it("returns null if non valid", function() {

            $collection = new Collection();
            $value = $collection->key();
            expect($value)->toBe(null);

        });

    });

    describe("->current()", function() {

        it("returns the current value", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $value = $collection->current();
            expect($value)->toBe(1);

        });

    });

    describe("->next()", function() {

        it("returns the next value", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $value = $collection->next();
            expect($value)->toBe(2);

        });

    });

    describe("->prev()", function() {

        it("navigates through collection", function() {

            $collection = new Collection(['data' => [1, 2, 3]]);
            $collection->rewind();
            expect($collection->next())->toBe(2);
            expect($collection->next())->toBe(3);
            expect($collection->next())->toBe(null);
            $collection->end();
            expect($collection->prev())->toBe(2);
            expect($collection->prev())->toBe(1);
            expect($collection->prev())->toBe(null);

        });

    });

    describe("->rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            expect($collection->end())->toBe(5);
            expect($collection->rewind())->toBe(1);

        });

    });

    describe("->valid()", function() {

        it("returns true only when the collection is valid", function() {

            $collection = new Collection();
            expect($collection->valid())->toBe(false);

            $collection = new Collection(['data' => [1, 5]]);
            expect($collection->valid())->toBe(true);

        });

    });

    describe("->count()", function() {

        it("returns 0 on empty", function() {

            $collection = new Collection();
            expect($collection)->toHaveLength(0);

        });

        it("returns the number of items in the collection", function() {

            $collection = new Collection(['data' => [5 ,null, 4, true, false, 'bob']]);
            expect($collection)->toHaveLength(6);

        });

    });

    describe("->merge()", function() {

        it("merges two collection", function() {

            $collection = new Collection(['data' => [1, 2, 3]]);
            $collection2 = new Collection(['data' => [4, 5, 6, 7]]);
            $collection->merge($collection2);

            expect($collection->data())->toBe([1, 2, 3, 4, 5, 6, 7]);

        });

        it("merges two collection with key preservation", function() {

            $collection = new Collection(['data' => [1, 2, 3]]);
            $collection2 = new Collection(['data' => [4, 5, 6, 7]]);
            $collection->merge($collection2, true);

            expect($collection->data())->toBe([4, 5, 6, 7]);

        });

    });

    describe("->embed()", function() {

        it("delegates the call up to the schema instance", function() {

            $model = Double::classname(['extends' => Model::class]);
            $schema = Double::instance();

            $model::definition($schema);

            $galleries = $model::create([], ['type' => 'set']);

            expect($schema)->toReceive('embed')->with($galleries, ['relation1.relation2']);
            $galleries->embed(['relation1.relation2']);

            $model::reset();

        });

    });

    describe("->data()", function() {

        it("calls `to()`", function() {

            $collection = new Collection(['data' => [
                1 => 1
            ]]);
            expect($collection)->toReceive('to')->with('array', []);

            $collection->data([]);

        });

    });

    describe("::toArray()", function() {

        it("converts a collection to an array", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            expect(Collection::toArray($collection))->toBe([1, 2, 3, 4, 5]);

        });

        it("converts objects which support __toString", function() {

            $stringable = Double::classname();
            allow($stringable)->toReceive('__toString')->andReturn('hello');
            $collection = new Collection(['data' => [new $stringable()]]);

            expect(Collection::toArray($collection))->toBe(['hello']);

        });

        it("converts objects using handlers", function() {

            $handlable = Double::classname();
            $handlers = [$handlable => function($value) { return 'world'; }];
            $collection = new Collection(['data' => [new $handlable()]]);

            expect(Collection::toArray($collection, compact('handlers')))->toBe(['world']);

        });

        it("doesn't convert unsupported objects", function() {

            $collection = new Collection(['data' => [(object) 'an object']]);
            expect(Collection::toArray($collection))->toEqual([(object) 'an object']);

        });

        it("converts nested collections", function() {

            $collection = new Collection([
                'data' => [
                    1, 2, 3, new Collection(['data' => [4, 5, 6]])
                ]
            ]);
            expect(Collection::toArray($collection))->toBe([1, 2, 3, [4, 5, 6]]);

        });

        it("converts mixed nested collections & arrays", function() {

            $collection = new Collection([
                'data' => [
                    1, 2, 3, [
                        new Collection(['data' => [4, 5, 6]])
                    ]
                ]
            ]);
            expect(Collection::toArray($collection))->toBe([1, 2, 3, [[4, 5, 6]]]);

        });

    });

});
