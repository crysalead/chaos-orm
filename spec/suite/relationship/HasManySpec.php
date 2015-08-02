<?php
namespace chaos\spec\suite\relationship;

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

            $hasMany->embed($galleries);

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

            $hasMany->embed($galleries, ['fetchOptions' => ['return' => 'array']]);

            foreach ($galleries as $gallery) {
                foreach ($gallery['images'] as $image) {
                    expect($gallery['id'])->toBe($image['gallery_id']);
                    expect($image)->toBeAn('array');
                }
            }

        });

    });

});