<?php
namespace chaos\spec\suite\model\collection;

use InvalidArgumentException;
use chaos\model\Model;
use chaos\model\collection\Collection;
use chaos\model\collection\Through;

use kahlan\plugin\Stub;
use chaos\spec\fixture\model\Image;
use chaos\spec\fixture\model\Tag;
use chaos\spec\fixture\model\ImageTag;

describe("Through", function() {

    beforeEach(function() {

        $this->images_tags = [];

         $this->imageTagModel = $imageTagModel = Stub::classname([
            'extends' => ImageTag::class
        ]);

        $this->tagModel = $tagModel = Stub::classname([
            'extends' => Tag::class
        ]);

        for ($i = 0; $i < 5; $i++) {
            $image_tag = new $imageTagModel();
            $tag = new $tagModel();
            $tag->name = $i;
            $image_tag->tag = $tag;
            $this->images_tags[] = $image_tag;
        }

        $this->image = new Image(['data' => [
            'id'          => 1,
            'name'        => 'amiga_1200.jpg',
            'title'       => 'Amiga 1200',
            'images_tags' => $this->images_tags
        ]]);

        $this->through = new Through([
            'parent'  => $this->image,
            'model'   => $this->tagModel,
            'through' => 'images_tags',
            'using'   => 'tag'
        ]);

    });

    describe("->parent()", function() {

        it("gets the parent", function() {

            expect($this->through->parent())->toBe($this->image);

        });

        it("sets a parent", function() {

            $parent = Stub::create();
            $this->through->parent($parent);
            expect($this->through->parent())->toBe($parent);

        });

    });

    describe("->rootPath()", function() {

        it("always returns an emtpy root path", function() {

            expect($this->through->rootPath())->toBe('');

        });

    });

    describe("->model()", function() {

        it("returns the model", function() {

            expect($this->through->model())->toBe($this->tagModel);

        });

    });

    describe("->meta()", function() {

        it("returns the meta attributes attached to the pivot collection", function() {

            $meta = ['meta' => ['page' => 5, 'limit' => 10]];
            $this->image->images_tags = new Collection(['meta' => $meta]);
            expect($this->through->meta())->toBe($meta);

        });

    });

    describe("->invoke()", function() {

        it("dispatches a method against all items in the collection", function() {

            Stub::on($this->tagModel)->method('hello', function() {
                return 'world';
            });

            foreach ($this->image->images_tags as $image_tag) {
                expect($image_tag->tag)->toReceive('hello');
            }

            $result = $this->through->invoke('hello');
            expect($result->data())->toBe(array_fill(0, 5, 'world'));

        });

    });

    describe("->each()", function() {

        it("applies a filter on a collection", function() {

            $filter = function($item) {
                $item->hello = 'world';
                return $item;
            };
            $result = $this->through->each($filter);

            foreach ($this->through as $tag) {
                expect($tag->hello)->toBe('world');
            }

            expect($this->through->count())->toBe(5);

        });

    });

    describe("->find()", function() {

        it("extracts items from a collection according a filter", function() {

            $filter = function($item) {
                return $item->name % 2 === 0;
            };

            $result = $this->through->find($filter);

            expect($result)->toBeAnInstanceOf(Collection::class);
            expect($result->data())->toBe([
                ['name' => 0],
                ['name' => 2],
                ['name' => 4]
            ]);

        });

    });

    describe("->map()", function() {

        it("applies a Closure to a copy of all data in the collection", function() {

            $filter = function($item) {
                $item->name = 'tag' . $item->name;
                return $item;
            };

            $result = $this->through->map($filter);

            expect($result)->toBeAnInstanceOf(Collection::class);
            expect($result->data())->toBe([
                ['name' => 'tag0'],
                ['name' => 'tag1'],
                ['name' => 'tag2'],
                ['name' => 'tag3'],
                ['name' => 'tag4']
            ]);

        });

    });

    describe("->reduce()", function() {

        it("reduces a collection down to a single value", function() {

            $filter = function($memo, $item) {
                return $memo + $item->name;
            };

            expect($this->through->reduce($filter, 0))->toBe(10);
            expect($this->through->reduce($filter, 1))->toBe(11);

        });

    });

    describe("->slice()", function() {

        it("extracts a slice of items", function() {

            $result = $this->through->slice(2, 2);

            expect($result)->toBeAnInstanceOf(Collection::class);
            expect($result->data())->toBe([
                ['name' => 2],
                ['name' => 3]
            ]);

        });

    });

    describe("->offsetExists()", function() {

        it("returns true if a element exist", function() {

            expect(isset($this->through[0]))->toBe(true);
            expect(isset($this->through[1]))->toBe(true);

        });

        it("returns false if a element doesn't exist", function() {

            expect(isset($this->through[10]))->toBe(false);

        });

    });

    describe("->offsetSet/offsetGet()", function() {

        it("allows array access", function() {

            expect($this->through[0]->data())->toBe(['name' => 0]);

        });

        it("sets at a specific key", function() {

            $this->through[0] = ['name' => 10];
            expect($this->through[0]->data())->toBe(['name' => 10]);
            expect($this->through)->toHaveLength(5);

        });

        it("adds a new item", function() {

            $this->through[] = ['name' => 5];
            expect($this->through)->toHaveLength(6);

            expect($this->through->data())->toBe([
                ['name' => 0],
                ['name' => 1],
                ['name' => 2],
                ['name' => 3],
                ['name' => 4],
                ['name' => 5]
            ]);

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            unset($this->through[1]);
            unset($this->through[2]);

            expect($this->through)->toHaveLength(3);
            expect($this->through->data())->toBe([
                ['name' => 0],
                ['name' => 3],
                ['name' => 4]
            ]);

        });

    });

    describe("->keys()", function() {

        it("returns the item keys", function() {

            expect($this->through->keys())->toBe([0, 1, 2, 3, 4]);

        });

    });

    describe("->key()", function() {

        it("returns current key", function() {

            $value = $this->through->key();
            expect($value)->toBe(0);

        });

        it("returns null if non valid", function() {

            $image = new Image(['data' => [
                'id'          => 1,
                'name'        => 'amiga_1200.jpg',
                'title'       => 'Amiga 1200'
            ]]);

            $through = new Through([
                'parent'  => $image,
                'model'   => $this->tagModel,
                'through' => 'images_tags',
                'using'   => 'tag'
            ]);
            $value = $through->key();
            expect($value)->toBe(null);

        });

    });

    describe("->current()", function() {

        it("returns the current value", function() {

            expect($this->through->current()->data())->toBe(['name' => 0]);

        });

    });

    describe("->next()", function() {

        it("returns the next value", function() {

            expect($this->through->next()->data())->toBe(['name' => 1]);

        });

    });

    describe("->prev()", function() {

        it("navigates through collection", function() {

            $this->through->rewind();
            expect($this->through->next()->data())->toBe(['name' => 1]);
            expect($this->through->next()->data())->toBe(['name' => 2]);
            expect($this->through->next()->data())->toBe(['name' => 3]);
            expect($this->through->next()->data())->toBe(['name' => 4]);
            expect($this->through->next())->toBe(null);
            $this->through->end();
            expect($this->through->prev()->data())->toBe(['name' => 3]);
            expect($this->through->prev()->data())->toBe(['name' => 2]);
            expect($this->through->prev()->data())->toBe(['name' => 1]);
            expect($this->through->prev()->data())->toBe(['name' => 0]);
            expect($this->through->prev())->toBe(null);

        });

    });

    describe("->rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            expect($this->through->end()->data())->toBe(['name' => 4]);
            expect($this->through->rewind()->data())->toBe(['name' => 0]);

        });

    });

    describe("->valid()", function() {

        it("returns `false` when the collection is not valid", function() {

            $image = new Image(['data' => [
                'id'          => 1,
                'name'        => 'amiga_1200.jpg',
                'title'       => 'Amiga 1200'
            ]]);

            $through = new Through([
                'parent'  => $image,
                'model'   => $this->tagModel,
                'through' => 'images_tags',
                'using'   => 'tag'
            ]);
            expect($through->valid())->toBe(false);

        });

        it("returns `true` only when the collection is valid", function() {

            expect($this->through->valid())->toBe(true);

        });

    });

    describe("->count()", function() {

        it("returns the number of items in the collection", function() {

            expect($this->through)->toHaveLength(5);

        });

    });

    describe("->merge()", function() {

        it("merges two collection", function() {

            $collection = new Collection(['data' => [
                ['name' => 5],
                ['name' => 6]
            ]]);

            $this->through->merge($collection);

            expect($this->through->data())->toBe([
                ['name' => 0],
                ['name' => 1],
                ['name' => 2],
                ['name' => 3],
                ['name' => 4],
                ['name' => 5],
                ['name' => 6]
            ]);

        });

        it("merges two collection with key preservation", function() {

            $collection = new Collection(['data' => [
                ['name' => 5],
                ['name' => 6]
            ]]);

            $this->through->merge($collection, true);

            expect($this->through->data())->toBe([
                ['name' => 5],
                ['name' => 6],
                ['name' => 2],
                ['name' => 3],
                ['name' => 4],
            ]);

        });

    });

    describe("->embed()", function() {

        it("deletages the call up to the schema instance", function() {

            $model = $this->tagModel;
            $schema = Stub::create();

            $model::config(['schema' => $schema]);

            expect($schema)->toReceive('embed')->with($this->through, ['relation1.relation2']);
            $this->through->embed(['relation1.relation2']);

        });

    });

    describe("->data()", function() {

        it("calls `toArray()`", function() {

            expect(Collection::class)->toReceive('::toArray')->with($this->through);

            $this->through->data();

        });

    });

});