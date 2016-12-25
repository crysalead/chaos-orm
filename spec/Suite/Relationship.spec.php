<?php
namespace Chaos\ORM\Spec\Suite;

use Chaos\ORM\ChaosException;

use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;
use Chaos\ORM\Spec\Fixture\Model\Tag;
use Chaos\ORM\Spec\Fixture\Model\Gallery;
use Chaos\ORM\Spec\Fixture\Model\GalleryDetail;

describe("Relationship", function() {

    afterEach(function() {
        Gallery::reset();
        Image::reset();
    });

    describe("->counterpart()", function() {

        it("returns the counterpart relationship for belongsTo/hasMany relations", function() {

            $relation = Image::definition()->relation('gallery');
            expect($relation->counterpart())->toBe(Gallery::definition()->relation('images'));

            $relation = Gallery::definition()->relation('images');
            expect($relation->counterpart())->toBe(Image::definition()->relation('gallery'));

        });

        it("returns the counterpart relationship for belongsTo/hasOne relations", function() {

            $relation = GalleryDetail::definition()->relation('gallery');
            expect($relation->counterpart())->toBe(Gallery::definition()->relation('detail'));

            $relation = Gallery::definition()->relation('detail');
            expect($relation->counterpart())->toBe(GalleryDetail::definition()->relation('gallery'));

        });

        it("returns the counterpart relationship for hasMany/hasMany relations", function() {

            $relation = Image::definition()->relation('tags');
            expect($relation->counterpart())->toBe(Tag::definition()->relation('images'));

            $relation = Tag::definition()->relation('images');
            expect($relation->counterpart())->toBe(Image::definition()->relation('tags'));

        });

        it("throws an exception when the counterpart is ambiguous", function() {

            $schema = Gallery::definition();
            $schema->hasMany('images', Image::class, [
                'keys' => ['id' => 'gallery_id']
            ]);
            $schema->hasMany('photos', Image::class, [
                'keys' => ['id' => 'gallery_id']
            ]);

            $closure = function() {
                $relation = Image::definition()->relation('gallery');
                $relation->counterpart();
            };

            expect($closure)->toThrow(new ChaosException("Ambiguous belongsTo counterpart relationship for `Chaos\ORM\Spec\Fixture\Model\Image`. Apply the Single Table Inheritance pattern to get unique models."));

        });

    });

});
