<?php
namespace Chaos\Spec\Suite\Relationship;

use Chaos\ChaosException;
use Chaos\Model;
use Chaos\Relationship;
use Chaos\Relationship\HasOne;
use Chaos\Conventions;

use Kahlan\Plugin\Stub;

use Chaos\Spec\Fixture\Model\Gallery;
use Chaos\Spec\Fixture\Model\GalleryDetail;

describe("HasOne", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');
    });

    describe("->__construct()", function() {

        it("creates a hasOne relationship", function() {

            $relation = new HasOne([
                'from' => Gallery::class,
                'to'   => GalleryDetail::class
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', GalleryDetail::class));

            $foreignKey = $this->conventions->apply('foreignKey', Gallery::class);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->from())->toBe(Gallery::class);
            expect($relation->to())->toBe(GalleryDetail::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new HasOne([
                    'to'   => GalleryDetail::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception if `'to'` is missing", function() {

            $closure = function() {
                $relation = new HasOne([
                    'from' => Gallery::class
                ]);
            };
            expect($closure)->toThrow(new ChaosException("The relationship `'to'` option can't be empty."));

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(GalleryDetail::class)->method('::all', function($options = [], $fetchOptions = []) {
                $details =  GalleryDetail::create([
                    ['id' => 1, 'description' => 'Foo Gallery Description', 'gallery_id' => 1],
                    ['id' => 2, 'description' => 'Bar Gallery Description', 'gallery_id' => 2]
                ], ['type' => 'set', 'exists' => true, 'collector' => $fetchOptions['collector']]);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $details->data();
                }
                return $details;
            });
        });

        it("embeds a hasOne relationship", function() {

            $hasOne = Gallery::relation('detail');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set', 'exists' => true]);

            expect(GalleryDetail::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => [1, 2]]
            ], ['collector' => $galleries->collector()]);

            $galleries->embed(['detail']);

            foreach ($galleries as $gallery) {
                expect($gallery->detail->gallery_id)->toBe($gallery->id);
                expect($gallery->detail->collector())->toBe($gallery->collector());
                expect($gallery->detail->collector())->toBe($galleries->collector());
            }

        });

        it("embeds a hasOne relationship using array hydration", function() {

            $hasOne = Gallery::relation('detail');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set', 'exists' => true]);

            $galleries = $galleries->data();

            expect(GalleryDetail::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => [1, 2]]
            ], ['collector' => null, 'return' => 'array']);

            $hasOne->embed($galleries, ['fetchOptions' => ['return' => 'array']]);

            foreach ($galleries as $gallery) {
                expect($gallery['detail']['gallery_id'])->toBe($gallery['id']);
                expect($gallery['detail'])->toBeAn('array');
            }

        });

    });

    describe("->get()", function() {

        it("returns `null` for unexisting foreign key", function() {

            Stub::on(GalleryDetail::class)->method('::all', function($options = [], $fetchOptions = []) {
                return GalleryDetail::create([], ['type' => 'set', 'exists' => true, 'collector' => $fetchOptions['collector']]);
            });

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            expect($gallery->detail)->toBe(null);

        });

        it("lazy loads a hasOne relation", function() {

            Stub::on(GalleryDetail::class)->method('::all', function($options = [], $fetchOptions = []) {
                $details =  GalleryDetail::create([
                    ['id' => 1, 'description' => 'Foo Gallery Description', 'gallery_id' => 1]
                ], ['type' => 'set', 'exists' => true, 'collector' => $fetchOptions['collector']]);
                return $details;
            });

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);

            expect(GalleryDetail::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => 1]
            ], ['collector' => $gallery->collector()]);

            expect($gallery->detail->gallery_id)->toBe($gallery->id);
            expect($gallery->detail->collector())->toBe($gallery->collector());

        });

    });

    describe("->save()", function() {

        it("bails out if no relation data hasn't been setted", function() {

            $hasOne = Gallery::relation('detail');
            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            expect($hasOne->save($gallery))->toBe(true);

        });

        it("saves a hasOne relationship", function() {

            $hasOne = Gallery::relation('detail');

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            $gallery->detail = ['description' => 'Foo Gallery Description'];

            Stub::on($gallery->detail)->method('save', function() use ($gallery) {
                $gallery->detail->id = 1;
                return true;
            });

            expect($gallery->detail)->toReceive('save');
            expect($hasOne->save($gallery))->toBe(true);
            expect($gallery->detail->gallery_id)->toBe($gallery->id);

        });

    });

});