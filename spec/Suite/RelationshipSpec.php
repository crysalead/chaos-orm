<?php
namespace Chaos\Spec\Suite;

use Chaos\ChaosException;

use Chaos\Spec\Fixture\Model\Image;
use Chaos\Spec\Fixture\Model\ImageTag;
use Chaos\Spec\Fixture\Model\Tag;
use Chaos\Spec\Fixture\Model\Gallery;
use Chaos\Spec\Fixture\Model\GalleryDetail;

describe("Relationship", function() {

    afterEach(function() {
        Gallery::reset();
        Image::reset();
    });

    describe("->counterpart()", function() {

        it("returns the counterpart relationship for belongsTo/hasMany relations", function() {

            $relation = Image::relation('gallery');
            expect($relation->counterpart())->toBe(Gallery::relation('images'));

            $relation = Gallery::relation('images');
            expect($relation->counterpart())->toBe(Image::relation('gallery'));

        });

        it("returns the counterpart relationship for belongsTo/hasOne relations", function() {

            $relation = GalleryDetail::relation('gallery');
            expect($relation->counterpart())->toBe(Gallery::relation('detail'));

            $relation = Gallery::relation('detail');
            expect($relation->counterpart())->toBe(GalleryDetail::relation('gallery'));

        });

        it("returns the counterpart relationship for hasMany/hasMany relations", function() {

            $relation = Image::relation('tags');
            expect($relation->counterpart())->toBe(Tag::relation('images'));

            $relation = Tag::relation('images');
            expect($relation->counterpart())->toBe(Image::relation('tags'));

        });

        it("throws an exception when the counterpart is ambiguous", function() {

            $schema = Gallery::schema();
            $schema->hasMany('images', Image::class, [
                'keys' => ['id' => 'gallery_id']
            ]);
            $schema->hasMany('photos', Image::class, [
                'keys' => ['id' => 'gallery_id']
            ]);

            $closure = function() {
                $relation = Image::relation('gallery');
                $relation->counterpart();
            };

            expect($closure)->toThrow(new ChaosException("Ambiguous belongsTo counterpart relationship for `Chaos\Spec\Fixture\Model\Image`. Apply the Single Table Inheritance pattern to get unique models."));

        });

    });

});
