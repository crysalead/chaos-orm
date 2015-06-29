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

        $this->tagModel = $tagModel = Stub::classname([
            'extends' => Tag::class
        ]);

        for ($i = 0; $i < 5; $i++) {
            $image_tag = new ImageTag();
            $image_tag->tag = new $tagModel();
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
            'model'   => Tag::class,
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

            expect($this->through->model())->toBe(Tag::class);

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
            expect($result->values())->toBe(array_fill(0, 5, 'world'));

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

            print_r($this->image->data());

        });

    });

});