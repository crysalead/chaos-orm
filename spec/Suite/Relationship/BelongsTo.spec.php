<?php
namespace Chaos\ORM\Spec\Suite\Relationship;

use Chaos\ORM\ORMException;
use Chaos\ORM\Model;
use Chaos\ORM\Relationship;
use Chaos\ORM\Relationship\BelongsTo;
use Chaos\ORM\Conventions;

use Kahlan\Plugin\Stub;

use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\Gallery;

describe("BelongsTo", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->key = $this->conventions->apply('key');
    });

    afterEach(function() {
        Gallery::reset();
        Image::reset();
    });

    describe("->__construct()", function() {

        it("creates a belongsTo relationship", function() {

            $relation = new BelongsTo([
                'from' => Image::class,
                'to'   => Gallery::class
            ]);

            expect($relation->name())->toBe($this->conventions->apply('field', Gallery::class));

            $foreignKey = $this->conventions->apply('reference', Gallery::class);
            expect($relation->keys())->toBe([$foreignKey => $this->key]);

            expect($relation->from())->toBe(Image::class);
            expect($relation->to())->toBe(Gallery::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->conventions())->toBeAnInstanceOf('Chaos\ORM\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new BelongsTo([
                    'to'   => Gallery::class
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception if `'to'` is missing", function() {

            $closure = function() {
                $relation = new BelongsTo([
                    'from' => Image::class
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'to'` option can't be empty."));

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(Gallery::class)->method('::all', function($options = [], $fetchOptions = []) {
                $galleries =  Gallery::create([
                    ['id' => 1, 'name' => 'Foo Gallery'],
                    ['id' => 2, 'name' => 'Bar Gallery']
                ], ['type' => 'set', 'exists' => true]);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $galleries->data();
                }
                return $galleries;
            });
        });

        it("embeds a belongsTo relationship", function() {

            $belongsTo = Image::definition()->relation('gallery');

            $images = Image::create([
                ['gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['gallery_id' => 1, 'title' => 'Las Vegas'],
                ['gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            expect(Gallery::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 2]]
            ], []);

            $images->embed(['gallery']);

            foreach ($images as $image) {
                expect($image->gallery->id)->toBe($image->gallery_id);
            }

        });

        it("embeds a belongsTo relationship using array hydration", function() {

            $belongsTo = Image::definition()->relation('gallery');

            $images = Image::create([
                ['gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['gallery_id' => 1, 'title' => 'Las Vegas'],
                ['gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            $images = $images->data();

            expect(Gallery::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 2]]
            ], ['return' => 'array']);

            $belongsTo->embed($images, ['fetchOptions' => ['return' => 'array']]);

            foreach ($images as $image) {
                expect($image['gallery']['id'])->toBe($image['gallery_id']);
                expect($image['gallery'])->toBeAn('array');
            }

        });

    });

    describe("->get()", function() {

        it("returns `null` for unexisting foreign key", function() {

            $image = Image::create(['id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
            expect($image->gallery)->toBe(null);

        });

        it("lazy loads a belongsTo relation", function() {

            Stub::on(Gallery::class)->method('::all', function($options = [], $fetchOptions = []) {
                $galleries =  Gallery::create([
                    ['id' => 1, 'name' => 'Foo Gallery']
                ], ['type' => 'set', 'exists' => true]);
                return $galleries;
            });

            $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);

            expect(Gallery::class)->toReceive('::all')->with([
                'conditions' => ['id' => 1]
            ], []);

            expect($image->gallery->id)->toBe($image->gallery_id);
        });

    });

    describe(".fetch()", function() {

        it("returns `null` for unexisting foreign key", function() {

            $image = Image::create(['id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
            expect($image->fetch('gallery'))->toBe(null);

        });

    });

    describe("->broadcast()", function() {

        it("bails out if no relation data hasn't been setted", function() {

            $belongsTo = Image::definition()->relation('gallery');
            $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200']);
            expect($belongsTo->broadcast($image))->toBe(true);

        });

        it("saves a belongsTo relationship", function() {

            $belongsTo = Image::definition()->relation('gallery');

            $image = Image::create(['id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
            $image->gallery = ['name' => 'Foo Gallery'];

            Stub::on($image->gallery)->method('broadcast', function() use ($image) {
                $image->gallery->id = 1;
                return true;
            });

            expect($image->gallery)->toReceive('broadcast');
            expect($belongsTo->broadcast($image))->toBe(true);
            expect($image->gallery_id)->toBe($image->gallery->id);

        });

        it("throws an exception if the saves relation didn't populate any ID", function() {

            $closure = function() {
                $belongsTo = Image::definition()->relation('gallery');

                $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);
                $image->gallery = ['name' => 'Foo Gallery'];

                Stub::on($image->gallery)->method('broadcast', function() {
                    return true;
                });

                $belongsTo->broadcast($image);
            };

            expect($closure)->toThrow(new ORMException("The `'id'` key is missing from related data."));

        });

    });

});