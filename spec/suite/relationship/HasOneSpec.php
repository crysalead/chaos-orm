<?php
namespace chaos\spec\suite\relationship;

use chaos\Model;
use chaos\Relationship;
use chaos\relationship\HasOne;
use chaos\Conventions;

use kahlan\plugin\Stub;
use chaos\spec\fixture\model\Gallery;
use chaos\spec\fixture\model\GalleryDetail;

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

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(GalleryDetail::class)->method('::all', function($options = [], $fetchOptions = []) {
                $details =  GalleryDetail::create([
                    ['id' => 1, 'description' => 'Foo Gallery Description', 'gallery_id' => 1],
                    ['id' => 2, 'description' => 'Bar Gallery Description', 'gallery_id' => 2]
                ], ['type' => 'set']);
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
            ], ['type' => 'set']);

            $hasOne->embed($galleries);

            foreach ($galleries as $gallery) {
                expect($gallery->detail->gallery_id)->toBe($gallery->id);
            }

        });

        it("embeds a hasOne relationship using array hydration", function() {

            $hasOne = Gallery::relation('detail');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set']);

            $galleries = $galleries->data();

            $hasOne->embed($galleries, ['fetchOptions' => ['return' => 'array']]);

            foreach ($galleries as $gallery) {
                expect($gallery['detail']['gallery_id'])->toBe($gallery['id']);
                expect($gallery['detail'])->toBeAn('array');
            }

        });

    });

});