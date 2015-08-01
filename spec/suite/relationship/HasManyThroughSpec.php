<?php
namespace chaos\spec\suite\relationship;

use chaos\ChaosException;
use chaos\Model;
use chaos\Relationship;
use chaos\relationship\HasManyThrough;
use chaos\Conventions;

use kahlan\plugin\Stub;
use chaos\spec\fixture\model\Image;
use chaos\spec\fixture\model\ImageTag;
use chaos\spec\fixture\model\Tag;

describe("HasManyThrough", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');
    });

    describe("->__construct()", function() {

        it("creates a hasManyThrough relationship", function() {

            $relation = new HasManyThrough([
                'from'    => Image::class,
                'through' => 'images_tags',
                'using'   => 'tag'
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', Tag::class));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', Image::class));

            $foreignKey = $this->conventions->apply('foreignKey', 'tag');
            expect($relation->keys())->toBe([$foreignKey => $this->primaryKey]);

            expect($relation->from())->toBe(Image::class);
            expect($relation->to())->toBe(Tag::class);
            expect($relation->through())->toBe('images_tags');
            expect($relation->using())->toBe($this->conventions->apply(
                'usingName',
                $this->conventions->apply('fieldName',
                Tag::class
            )));
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

        });

        it("throws an exception is `'through'` is not set", function() {

            $closure = function() {
                $relation = new HasManyThrough([
                    'from' => Image::class,
                    'to'   => Tag::class
                ]);
            };

            expect($closure)->toThrow(new ChaosException("`'through'` option can't be empty for a has many through relation."));

        });

    });

    describe("->embed()", function() {

        beforeEach(function() {
            Stub::on(ImageTag::class)->method('::all', function($options = [], $fetchOptions = []) {
                $imagesTags =  ImageTag::create([
                    ['id' => 1, 'image_id' => 1, 'tag_id' => 1],
                    ['id' => 2, 'image_id' => 1, 'tag_id' => 3],
                    ['id' => 3, 'image_id' => 2, 'tag_id' => 5],
                    ['id' => 4, 'image_id' => 3, 'tag_id' => 6],
                    ['id' => 5, 'image_id' => 4, 'tag_id' => 6],
                    ['id' => 6, 'image_id' => 4, 'tag_id' => 3],
                    ['id' => 7, 'image_id' => 4, 'tag_id' => 1]
                ], ['type' => 'set']);
                if (empty($fetchOptions['return'])) {
                    return $imagesTags;
                }
                if ($fetchOptions['return'] === 'array') {
                    return $imagesTags->data();
                }
                if ($fetchOptions['return'] === 'object') {
                    return json_decode(json_encode($imagesTags->data()));
                }
            });

            Stub::on(Tag::class)->method('::all', function($options = [], $fetchOptions = []) {
                $tags =  Tag::create([
                    ['id' => 1, 'name' => 'High Tech'],
                    ['id' => 2, 'name' => 'Sport'],
                    ['id' => 3, 'name' => 'Computer'],
                    ['id' => 4, 'name' => 'Art'],
                    ['id' => 5, 'name' => 'Science'],
                    ['id' => 6, 'name' => 'City']
                ], ['type' => 'set']);
                if (empty($fetchOptions['return'])) {
                    return $tags;
                }
                if ($fetchOptions['return'] === 'array') {
                    return $tags->data();
                }
                if ($fetchOptions['return'] === 'object') {
                    return json_decode(json_encode($tags->data()));
                }
            });
        });

        it("embeds a hasManyThrough relationship", function() {

            $hasManyThrough = Image::relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            $hasManyThrough->embed($images);

            foreach ($images as $image) {
                foreach ($image->images_tags as $index => $image_tag) {
                    expect($image_tag->tag)->toBe($image->tags[$index]);
                }
            }

        });

        it("embeds a hasManyThrough relationship using object hydration", function() {

            $hasManyThrough = Image::relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            $images = json_decode(json_encode($images->data()));

            $hasManyThrough->embed($images, ['fetchOptions' => ['return' => 'object']]);

            foreach ($images as $image) {
                foreach ($image->images_tags as $index => $image_tag) {
                    expect($image_tag->tag)->toBe($image->tags[$index]);
                    expect($image->tags[$index])->toBeAnInstanceOf('stdClass');
                }
            }

        });

        it("embeds a hasManyThrough relationship using array hydration", function() {

            $hasManyThrough = Image::relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set']);

            $images = $images->data();

            $hasManyThrough->embed($images, ['fetchOptions' => ['return' => 'array']]);

            foreach ($images as $image) {
                foreach ($image['images_tags'] as $index => $image_tag) {
                    expect($image_tag['tag'])->toBe($image['tags'][$index]);
                    expect($image['tags'][$index])->toBeAn('array');
                }
            }

        });

    });

});