<?php
namespace Chaos\ORM\Spec\Suite\Collection;

use InvalidArgumentException;
use Chaos\ORM\Model;
use Chaos\ORM\Collection\Collection;
use Chaos\ORM\Collection\Through;

use Kahlan\Plugin\Double;

use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\Tag;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;

describe("Through", function() {

    beforeEach(function() {

        $this->images_tags = [];

         $this->imageTagModel = $imageTagModel = Double::classname([
            'extends' => ImageTag::class
        ]);

        $imageTagModel::definition()->lock(false);

        $this->tagModel = $tagModel = Double::classname([
            'extends' => Tag::class,
            'methods' => ['tagMethod']
        ]);

        $tagModel::definition()->lock(false);

        allow($tagModel)->toReceive('tagMethod')->andRun(function($options) {
            return $options;
        });

        for ($i = 0; $i < 5; $i++) {
            $image_tag = new $imageTagModel();
            $tag = new $tagModel();
            $tag->name = (string) $i;
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
            'schema'  => $tagModel::definition(),
            'parent'  => $this->image,
            'through' => 'images_tags',
            'using'   => 'tag'
        ]);

        $this->image->tags = $this->through;

    });

    describe("->parents()", function() {

        it("gets the parents", function() {

            expect($this->through->parents()->get($this->image))->toBe('tags');

        });

    });

    describe("->removeParent()", function() {

        it("removes a parent", function() {

            unset($this->image->tags);
            expect($this->through->parents()->has($this->image))->toBe(false);

        });

    });

    describe("->disconnect()", function() {

        it("removes a document from its graph", function() {

            $this->image->tags->disconnect();
            expect($this->through->parents()->has($this->image))->toBe(false);
            expect($this->image->has('tags'))->toBe(false);

        });

    });

    describe("->basePath()", function() {

        it("always returns an emtpy root path", function() {

            expect($this->through->basePath())->toBe('');

        });

    });

    describe("->schema()", function() {

        it("returns the schema", function() {

            $tagModel = $this->tagModel;
            expect($this->through->schema())->toBe($tagModel::definition());

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

            foreach ($this->image->images_tags as $image_tag) {
                expect($image_tag->tag)->toReceive('tagMethod');
            }

            $result = $this->through->invoke('tagMethod', ['world']);
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

    describe("->filter()", function() {

        it("extracts items from a collection according a filter", function() {

            $filter = function($item) {
                return $item->name % 2 === 0;
            };

            $result = $this->through->filter($filter);

            expect($result)->toBeAnInstanceOf(Collection::class);
            expect($result->data())->toBe([
                ['name' => "0"],
                ['name' => "2"],
                ['name' => "4"]
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
                return $memo + (integer) $item->name;
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
                ['name' => "2"],
                ['name' => "3"]
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

            expect($this->through[0]->data())->toBe(['name' => "0"]);

        });

        it("sets at a specific key", function() {

            $this->through[0] = ['name' => "10"];
            expect($this->through[0]->data())->toBe(['name' => "10"]);
            expect($this->through)->toHaveLength(5);

        });

        it("adds a new item", function() {

            $this->through[] = ['name' => "5"];
            expect($this->through)->toHaveLength(6);

            expect($this->through->data())->toBe([
                ['name' => "0"],
                ['name' => "1"],
                ['name' => "2"],
                ['name' => "3"],
                ['name' => "4"],
                ['name' => "5"]
            ]);

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            unset($this->through[1]);
            unset($this->through[2]);

            expect($this->through)->toHaveLength(3);
            expect($this->through->data())->toBe([
                ['name' => "0"],
                ['name' => "3"],
                ['name' => "4"]
            ]);

        });

    });

    describe("->has()", function() {

        it("delegates to `offsetExists`", function() {

            expect($this->through)->toReceive('offsetExists')->with(0);
            $this->through->has(0);

        });

    });

    describe("->remove()", function() {

        it("delegates to `offsetUnset`", function() {

            expect($this->through)->toReceive('offsetUnset')->with(0);
            $this->through->remove(0);

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

            $tagModel = $this->tagModel;

            $through = new Through([
                'parent'  => $image,
                'schema'  => $tagModel::definition(),
                'through' => 'images_tags',
                'using'   => 'tag'
            ]);
            $value = $through->key();
            expect($value)->toBe(null);

        });

    });

    describe("->current()", function() {

        it("returns the current value", function() {

            expect($this->through->current()->data())->toBe(['name' => "0"]);

        });

    });

    describe("->next()", function() {

        it("returns the next value", function() {

            expect($this->through->next()->data())->toBe(['name' => "1"]);

        });

    });

    describe("->prev()", function() {

        it("navigates through collection", function() {

            $this->through->rewind();
            expect($this->through->next()->data())->toBe(['name' => "1"]);
            expect($this->through->next()->data())->toBe(['name' => "2"]);
            expect($this->through->next()->data())->toBe(['name' => "3"]);
            expect($this->through->next()->data())->toBe(['name' => "4"]);
            expect($this->through->next())->toBe(null);
            $this->through->end();
            expect($this->through->prev()->data())->toBe(['name' => "3"]);
            expect($this->through->prev()->data())->toBe(['name' => "2"]);
            expect($this->through->prev()->data())->toBe(['name' => "1"]);
            expect($this->through->prev()->data())->toBe(['name' => "0"]);
            expect($this->through->prev())->toBe(null);

        });

    });

    describe("->rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            expect($this->through->end()->data())->toBe(['name' => "4"]);
            expect($this->through->rewind()->data())->toBe(['name' => "0"]);

        });

    });

    describe("->valid()", function() {

        it("returns `false` when the collection is not valid", function() {

            $image = new Image(['data' => [
                'id'          => 1,
                'name'        => 'amiga_1200.jpg',
                'title'       => 'Amiga 1200'
            ]]);

            $tagModel = $this->tagModel;

            $through = new Through([
                'parent'  => $image,
                'schema'  => $tagModel::definition(),
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
                ['name' => "5"],
                ['name' => "6"]
            ]]);

            $this->through->merge($collection);

            expect($this->through->data())->toBe([
                ['name' => "0"],
                ['name' => "1"],
                ['name' => "2"],
                ['name' => "3"],
                ['name' => "4"],
                ['name' => "5"],
                ['name' => "6"]
            ]);

        });

        it("merges two collection with key preservation", function() {

            $collection = new Collection(['data' => [
                ['name' => "5"],
                ['name' => "6"]
            ]]);

            $this->through->merge($collection, true);

            expect($this->through->data())->toBe([
                ['name' => "5"],
                ['name' => "6"],
                ['name' => "2"],
                ['name' => "3"],
                ['name' => "4"],
            ]);

        });

    });

    describe("->embed()", function() {

        it("delegates the call up to the schema instance", function() {

            $model = $this->tagModel;
            $schema = $model::definition();
            allow($schema)->toReceive('embed');

            expect($schema)->toReceive('embed')->with($this->through, ['relation1.relation2']);
            $this->through->embed(['relation1.relation2']);

        });

    });

    describe("->data()", function() {

        it("calls `to()`", function() {

            expect($this->through)->toReceive('to')->with('array', []);

            $this->through->data([]);

        });

    });

    describe("->validates()", function() {

        it("returns `true` when no validation error occur", function() {

            $image = Image::create();
            $image->tags[] = Tag::create();
            $image->tags[] = Tag::create();

            expect($image->tags->validates())->toBe(true);

        });

        it("returns `false` when a validation error occurs", function() {

            $validator = Tag::validator();
            $validator->rule('name', 'not:empty');

            $image = Image::create();
            $image->tags[] = Tag::create();
            $image->tags[] = Tag::create();

            expect($image->tags->validates())->toBe(false);

            expect($image->tags->errors())->toBe([
                [
                    'name' => [
                        'is required'
                    ]
                ],
                [
                    'name' => [
                        'is required'
                    ]
                ]
            ]);

        });

    });

    describe("->errors()", function() {

        it("returns errors", function() {

            $validator = Tag::validator();
            $validator->rule('name', 'not:empty');

            $image = Image::create();
            $image->tags[] = Tag::create();
            $image->tags[] = Tag::create();

            expect($image->validates())->toBe(false);

            expect($image->tags->errors())->toBe([
                [
                    'name' => [
                        'is required'
                    ]
                ],
                [
                    'name' => [
                        'is required'
                    ]
                ]
            ]);

        });

    });

});