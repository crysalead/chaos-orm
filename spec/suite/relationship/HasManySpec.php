<?php
namespace chaos\spec\suite\relationship;

use chaos\ChaosException;
use chaos\Model;
use chaos\Relationship;
use chaos\relationship\HasMany;
use chaos\Conventions;

use kahlan\plugin\Stub;
use chaos\spec\fixture\model\Image;
use chaos\spec\fixture\model\Gallery;

describe("HasMany", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');
    });

    describe("->__construct()", function() {

        it("creates a hasMany relationship", function() {

            $relation = new HasMany([
                'from' => Gallery::class,
                'to'   => Image::class
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', Image::class));

            $foreignKey = $this->conventions->apply('foreignKey', Gallery::class);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->from())->toBe(Gallery::class);
            expect($relation->to())->toBe(Image::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new HasMany([
                    'to'   => Image::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception if `'to'` is missing", function() {

            $closure = function() {
                $relation = new HasMany([
                    'from' => Gallery::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'to'` option can't be empty."));

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([
                    ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                    ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                    ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                    ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                    ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
                ], ['type' => 'set']);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $images->data();
                }
                return $images;
            });
        });

        it("embeds a hasMany relationship", function() {

            $hasMany = Gallery::relation('images');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set']);

            expect(Image::class)->toReceive('::all')->with([
                'query'   => ['conditions' => ['gallery_id' => [1, 2]]],
                'handler' => null
            ], ['collector' => $galleries->collector()]);

            $galleries->embed(['images']);

            foreach ($galleries as $gallery) {
                foreach ($gallery->images as $image) {
                    expect($gallery->id)->toBe($image->gallery_id);
                }
            }

        });

        it("embeds a hasMany relationship using array hydration", function() {

            $hasMany = Gallery::relation('images');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set']);

            $galleries = $galleries->data();

            expect(Image::class)->toReceive('::all')->with([
                'handler' => null,
                'query'   => ['conditions' => ['gallery_id' => [1, 2]]]
            ], ['collector' => null, 'return' => 'array']);

            $hasMany->embed($galleries, ['fetchOptions' => ['return' => 'array']]);

            foreach ($galleries as $gallery) {
                foreach ($gallery['images'] as $image) {
                    expect($gallery['id'])->toBe($image['gallery_id']);
                    expect($image)->toBeAn('array');
                }
            }

        });

    });

    describe("->get()", function() {

        it("lazy loads a hasMany relation", function() {

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([
                    ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                    ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                    ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas']
                ], ['type' => 'set']);
                return $images;
            });

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);

            expect(Image::class)->toReceive('::all')->with([
                'handler' => null,
                'query'   => ['conditions' => ['gallery_id' => 1]]
            ], ['collector' => $gallery->collector()]);

            foreach ($gallery->images as $image) {
                expect($gallery->id)->toBe($image->gallery_id);
            }

        });

    });

    describe("->save()", function() {

        it("bails out if no relation data hasn't been setted", function() {

            $hasMany = Gallery::relation('images');
            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            expect($hasMany->save($gallery))->toBe(true);

        });

        it("saves a hasMany relationship", function() {

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([], ['type' => 'set']);
                return $images;
            });

            $hasMany = Gallery::relation('images');

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            $gallery->images = [['title' => 'Amiga 1200']];

            Stub::on($gallery->images[0])->method('save', function() use ($gallery) {
                $gallery->images[0]->id = 1;
                return true;
            });

            expect($gallery->images[0])->toReceive('save');
            expect($hasMany->save($gallery))->toBe(true);
            expect($gallery->images[0]->gallery_id)->toBe($gallery->id);

        });

        it("assures old hasMany relations are removed", function() {

            $toDelete = Image::create(['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'], ['exists' => true]);

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) use ($toDelete){
                $images =  Image::create([$toDelete], ['type' => 'set']);
                return $images;
            });

            $hasMany = Gallery::relation('images');

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            $gallery->images = [['title' => 'Amiga 1200']];

            Stub::on($gallery->images[0])->method('save', function() use ($gallery) {
                $gallery->images[0]->id = 1;
                return true;
            });

            $schema = Image::schema();
            Stub::on($schema)->method('delete', function() {
                return true;
            });

            expect($gallery->images[0])->toReceive('save');
            expect($schema)->toReceive('delete')->with(['id' => 2]);
            expect($hasMany->save($gallery))->toBe(true);
            expect($gallery->images[0]->gallery_id)->toBe($gallery->id);

        });

    });

});
