<?php
namespace chaos\spec\suite\relationship;

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
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', Image::class));

            $foreignKey = $this->conventions->apply('foreignKey', Image::class);
            expect($relation->keys())->toBe([$foreignKey => $this->primaryKey]);

            expect($relation->from())->toBe(Image::class);
            expect($relation->to())->toBe(Gallery::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

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

            $belongsTo->embed($images);

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

            $belongsTo->embed($images, ['fetchOptions' => ['return' => 'array']]);

            foreach ($images as $image) {
                expect($image['gallery_id'])->toBe($image['gallery']['id']);
                expect($image['gallery'])->toBeAn('array');
            }

        });

    });

});