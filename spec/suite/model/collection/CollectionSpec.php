<?php
namespace chaos\spec\suite\model\collection;

use InvalidArgumentException;
use chaos\model\Model;
use chaos\model\collection\Collection;
use chaos\source\database\Query;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("Collection", function() {

	describe("->__construct()", function() {

        it("loads the data", function() {

            $collection = new Collection(['data' => ['foo']]);
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

    });

    describe("->exists()", function() {

        it("returns the exists value", function() {

            $collection = new Collection(['exists' => true]);
            expect($collection->exists())->toBe(true);

        });

    });

    describe("->parent()", function() {

        it("sets a parent", function() {

            $parent = Stub::create();
            $collection = new Collection();
            $collection->parent($parent);
            expect($collection->parent())->toBe($parent);

        });

        it("returns the parent", function() {

            $parent = Stub::create();
            $collection = new Collection(['parent' => $parent]);
            expect($collection->parent())->toBe($parent);

        });

    });

    describe("->rootPath()", function() {

        it("returns the root path", function() {

            $collection = new Collection(['rootPath' => 'items']);
            expect($collection->rootPath())->toBe('items');

        });

    });

    describe("->model()", function() {

        it("returns the model", function() {

            $collection = new Collection(['model' => 'chaos\model\Model']);
            expect($collection->model())->toBe('chaos\model\Model');

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
            $class = Stub::classname();

            Stub::on($class)->method('hello', function() {
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
            expect($result->values())->toBe(array_fill(0, 5, 'world'));

        });

    });

    describe("->each()", function() {

        it("applies a filter on a collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $filter = function($item) { return ++$item; };
            $result = $collection->each($filter);

            expect($result)->toBe($collection);
            expect($result->values())->toBe([2, 3, 4, 5, 6]);

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
            expect($result)->toBeAnInstanceOf('chaos\model\collection\Collection');
            expect($result->values())->toBe(array_fill(0, 10, 1));

        });

    });

    describe("->map()", function() {

        it("applies a Closure to a copy of all data in the collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            $filter = function($item) { return ++$item; };
            $result = $collection->map($filter);

            expect($result)->not->toBe($collection);
            expect($result->values())->toBe([2, 3, 4, 5, 6]);

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
            expect($result->values())->toBe([3, 4]);

        });

    });

    describe("->sort()", function() {

        it("sorts a collection", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            $result = $collection->sort();
            expect($result->values())->toBe([1, 2, 3, 4, 5]);

        });

        it("sorts a collection using a compare function", function() {

            $collection = new Collection(['data' => ['Alan', 'Dave', 'betsy', 'carl']]);
            $result = $collection->sort('strcasecmp');
            expect($result->values())->toBe(['Alan', 'betsy', 'carl', 'Dave']);

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

    });

    describe("->offsetSet/offsetGet()", function() {

        it("allows array access", function() {

            $collection = new Collection();
            $collection[] = 'foo';
            expect($collection[0])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

        it("sets at a specific key", function() {

            $collection = new Collection();
            $collection['mykey'] = 'foo';
            expect($collection['mykey'])->toBe('foo');
            expect($collection)->toHaveLength(1);

        });

        context("when a model is defined", function() {

            beforeEach(function() {
                $model = $this->model = Stub::classname(['extends' => 'chaos\model\Model']);
            });

            afterEach(function() {
                $model = $this->model;
                Model::reset();
            });

            it("autoboxes setted data", function() {

                $collection = new Collection([
                    'model' => $this->model
                ]);

                $collection[] = [
                    'id'      => 1,
                    'title'   => 'first record',
                    'enabled' => 1,
                    'created' => time()
                ];
                $entity = $collection[0];
                expect($entity)->toBeAnInstanceOf($this->model);
                expect($entity->parent())->toBe($collection);
                expect($entity->rootPath())->toBe(null);

            });

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->values())->toBe([5, 1, 2]);

        });

        it("unsets items but keeps index", function() {

            $collection = new Collection(['data' => [5, 3, 4, 1, 2]]);
            unset($collection[1]);
            unset($collection[2]);

            expect($collection)->toHaveLength(3);
            expect($collection->values())->toBe([5, 1, 2]);
            expect($collection->keys())->toBe([0, 3, 4]);

        });


        it("unsets all items in a foreach", function() {

            $data = ['Delete me', 'Delete me'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                unset($collection[$i]);
            }
            expect($collection->values())->toBe([]);

        });

        it("unsets last items in a foreach", function() {

            $data = ['Hello', 'Hello again!', 'Delete me'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }
            expect($collection->values())->toBe(['Hello', 'Hello again!']);

        });

        it("unsets first items in a foreach", function() {

            $data = ['Delete me', 'Hello', 'Hello again!'];
            $collection = new Collection(compact('data'));

            foreach ($collection as $i => $word) {
                if ($word === 'Delete me') {
                    unset($collection[$i]);
                }
            }

            expect($collection->values())->toBe(['Hello', 'Hello again!']);

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

    describe("->keys()", function() {

        it("returns the item keys", function() {

            $collection = new Collection(['data' => [
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ]]);
            expect($collection->keys())->toBe(['key1', 'key2', 'key3']);

        });

    });

    describe("->values()", function() {

        it("returns the item values", function() {

            $collection = new Collection(['data' => [
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ]]);
            expect($collection->values())->toBe(['one', 'two', 'three']);

        });

    });

    describe("->raw()", function() {

        it("returns the raw data", function() {

            $data = [
                'key1' => 'one',
                'key2' => 'two',
                'key3' => 'three'
            ];
            $collection = new Collection(compact('data'));
            expect($collection->raw())->toBe($data);

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

    describe("->first/rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            $collection = new Collection(['data' => [1, 2, 3, 4, 5]]);
            expect($collection->end())->toBe(5);
            expect($collection->rewind())->toBe(1);
            expect($collection->end())->toBe(5);
            expect($collection->first())->toBe(1);

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

            expect($collection->values())->toBe([1, 2, 3, 4, 5, 6, 7]);

        });

        it("merges two collection with key preservation", function() {

            $collection = new Collection(['data' => [1, 2, 3]]);
            $collection2 = new Collection(['data' => [4, 5, 6, 7]]);
            $collection->merge($collection2, true);

            expect($collection->values())->toBe([4, 5, 6, 7]);

        });

    });

    describe("->data()", function() {

        it("calls `toArray()`", function() {

            $collection = new Collection(['data' => [
                1 => 1
            ]]);
            expect('chaos\model\collection\Collection')->toReceive('::toArray')->with($collection);

            $collection->data();

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            $this->connection = box('chaos.spec')->get('source.database.mysql');
            $this->fixtures = new Fixtures([
                'connection' => $this->connection,
                'fixtures'   => [
                    'gallery'   => 'chaos\spec\fixture\schema\Gallery',
                    'image'     => 'chaos\spec\fixture\schema\Image',
                    'image_tag' => 'chaos\spec\fixture\schema\ImageTag',
                    'tag'       => 'chaos\spec\fixture\schema\Tag'
                ]
            ]);

            $this->fixtures->populate('gallery');
            $gallery = $this->fixtures->get('gallery')->model();

            $this->query = new Query([
                'model'      => $gallery,
                'connection' => $this->connection
            ]);

            $this->query->order(['id']);

            $this->fixtures->populate('image');
            $this->fixtures->populate('image_tag');
            $this->fixtures->populate('tag');
        });

        afterEach(function() {
            $this->fixtures->drop();
        });

        it("finds all records with their relation", function() {

            $galleries = $this->query->all();
            $galleries->embed(['image.tags']);
            expect($galleries->data())->toBe([]);

        });

    });

    describe("::toArray()", function() {

        it("converts a collection to an array", function() {

            $collection = new Collection(['data' => [
                1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5
            ]]);
            expect(Collection::toArray($collection))->toBe([
                1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5
            ]);

        });

        it("converts objects which support __toString", function() {

            $stringable = Stub::classname();
            Stub::on($stringable)->method('__toString')->andReturn('hello');
            $collection = new Collection(['data' => [new $stringable()]]);

            expect(Collection::toArray($collection))->toBe(['hello']);

        });

        it("converts objects using handlers", function() {

            $handlable = Stub::classname();
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
