<?php
namespace chaos\spec\suite\relationship;

use chaos\ChaosException;
use chaos\Model;
use chaos\Relationship;
use chaos\relationship\BelongsTo;
use chaos\Conventions;

use kahlan\plugin\Stub;
use chaos\spec\fixture\model\Image;
use chaos\spec\fixture\model\Gallery;

describe("BelongsTo", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');
    });

    describe("->__construct()", function() {

        it("creates a belongsTo relationship", function() {

            $relation = new BelongsTo([
                'from' => Image::class,
                'to'   => Gallery::class
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', Gallery::class));

            $foreignKey = $this->conventions->apply('foreignKey', Image::class);
            expect($relation->keys())->toBe([$foreignKey => $this->primaryKey]);

            expect($relation->from())->toBe(Image::class);
            expect($relation->to())->toBe(Gallery::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new BelongsTo([
                    'to'   => Gallery::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception if `'to'` is missing", function() {

            $closure = function() {
                $relation = new BelongsTo([
                    'from' => Image::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'to'` option can't be empty."));

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(Gallery::class)->method('::all', function($options = [], $fetchOptions = []) {
                $galleries =  Gallery::create([
                    ['id' => 1, 'name' => 'Foo Gallery'],
                    ['id' => 2, 'name' => 'Bar Gallery']
                ], ['type' => 'set']);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $galleries->data();
                }
                return $galleries;
            });
        });

        it("embeds a belongsTo relationship", function() {

            $belongsTo = Image::relation('gallery');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            expect(Gallery::class)->toReceive('::all')->with([
                'query'   => ['conditions' => ['id' => [1, 2]]],
                'handler' => null
            ], ['collector' => $images->collector()]);

            $images->embed(['gallery']);

            foreach ($images as $image) {
                expect($image->gallery_id)->toBe($image->gallery->id);
            }

        });

        it("embeds a belongsTo relationship using array hydration", function() {

            $belongsTo = Image::relation('gallery');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            $images = $images->data();

            expect(Gallery::class)->toReceive('::all')->with([
                'handler' => null,
                'query'   => ['conditions' => ['id' => [1, 2]]]
            ], ['collector' => null, 'return' => 'array']);

            $belongsTo->embed($images, ['fetchOptions' => ['return' => 'array']]);

            foreach ($images as $image) {
                expect($image['gallery_id'])->toBe($image['gallery']['id']);
                expect($image['gallery'])->toBeAn('array');
            }

        });

    });

    describe("->get()", function() {

        it("lazy loads a belongsTo relation", function() {

            Stub::on(Gallery::class)->method('::all', function($options = [], $fetchOptions = []) {
                $galleries =  Gallery::create([
                    ['id' => 1, 'name' => 'Foo Gallery']
                ], ['type' => 'set']);
                return $galleries;
            });

            $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);

            expect(Gallery::class)->toReceive('::all')->with([
                'handler' => null,
                'query'   => ['conditions' => ['id' => 1]]
            ], ['collector' => $image->collector()]);

            expect($image->gallery_id)->toBe($image->gallery->id);

        });

    });

    describe("->save()", function() {

        it("bails out if no relation data hasn't been setted", function() {

            $belongsTo = Image::relation('gallery');
            $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200']);
            expect($belongsTo->save($image))->toBe(true);

        });

        it("saves a belongsTo relationship", function() {

            $belongsTo = Image::relation('gallery');

            $image = Image::create(['id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
            $image->gallery = ['name' => 'Foo Gallery'];

            Stub::on($image->gallery)->method('save', function() use ($image) {
                $image->gallery->id = 1;
                return true;
            });

            expect($image->gallery)->toReceive('save');

            expect($belongsTo->save($image))->toBe(true);

            expect($image->gallery_id)->toBe($image->gallery->id);

        });

        it("throws an exception if the saves relation didn't populate any ID", function() {

            $closure = function() {
                $belongsTo = Image::relation('gallery');

                $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
                $image->gallery = ['name' => 'Foo Gallery'];

                Stub::on($image->gallery)->method('save', function() {
                    return true;
                });

                $belongsTo->save($image);
            };

            expect($closure)->toThrow(new ChaosException("The `'id'` key is missing from related data."));

        });

    });

});