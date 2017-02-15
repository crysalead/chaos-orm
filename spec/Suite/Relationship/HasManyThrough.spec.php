<?php
namespace Chaos\ORM\Spec\Suite\Relationship;

use Chaos\ORM\ORMException;
use Chaos\ORM\Model;
use Chaos\ORM\Relationship;
use Chaos\ORM\Relationship\HasManyThrough;
use Chaos\ORM\Conventions;

use Kahlan\Plugin\Stub;

use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;
use Chaos\ORM\Spec\Fixture\Model\Tag;

describe("HasManyThrough", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->key = $this->conventions->apply('key');
    });

    afterEach(function() {
        Image::reset();
        ImageTag::reset();
        Tag::reset();
    });

    describe("->__construct()", function() {

        it("creates a hasManyThrough relationship", function() {

            $relation = new HasManyThrough([
                'from'    => Image::class,
                'through' => 'images_tags',
                'using'   => 'tag'
            ]);

            expect($relation->name())->toBe($this->conventions->apply('field', Tag::class));

            $foreignKey = $this->conventions->apply('reference', 'tag');
            expect($relation->keys())->toBe([$foreignKey => $this->key]);

            expect($relation->from())->toBe(Image::class);
            expect($relation->to())->toBe(Tag::class);
            expect($relation->through())->toBe('images_tags');
            expect($relation->using())->toBe($this->conventions->apply(
                'single',
                $this->conventions->apply('field',
                Tag::class
            )));
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('Chaos\ORM\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new HasManyThrough([
                    'through' => 'images_tags',
                    'using'   => 'tag'
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception is `'through'` is not set", function() {

            $closure = function() {
                $relation = new HasManyThrough([
                    'from'    => Image::class,
                    'using'   => 'tag'
                ]);
            };

            expect($closure)->toThrow(new ORMException("The relationship `'through'` option can't be empty."));

        });

        it("throws an exception if `'using'` is missing", function() {

            $closure = function() {
                $relation = new HasManyThrough([
                    'from'    => Image::class,
                    'through' => 'images_tags'
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'using'` option can't be empty."));

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
                ], ['type' => 'set', 'exists' => true]);
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
                ], ['type' => 'set', 'exists' => true]);
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

            $hasManyThrough = Image::definition()->relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set', 'exists' => true]);

            expect(ImageTag::class)->toReceive('::all')->with([
                'conditions' => ['image_id' => [1, 2, 3, 4, 5]]
            ], []);

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 3, 5, 6]]
            ], []);

            $images->embed(['tags']);

            foreach ($images as $image) {
                foreach ($image->images_tags as $index => $image_tag) {
                    expect($image->tags[$index])->toBe($image_tag->tag);
                }
            }

        });

        it("embeds a hasManyThrough relationship using object hydration", function() {

            $hasManyThrough = Image::definition()->relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set', 'exists' => true]);

            $images = json_decode(json_encode($images->data()));

            expect(ImageTag::class)->toReceive('::all')->with([
                'conditions' => ['image_id' => [1, 2, 3, 4, 5]]
            ], ['return' => 'object']);

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 3, 5, 6]]
            ], ['return' => 'object']);

            $hasManyThrough->embed($images, ['fetchOptions' => ['return' => 'object']]);

            foreach ($images as $image) {
                foreach ($image->images_tags as $index => $image_tag) {
                    expect($image_tag->tag)->toBe($image->tags[$index]);
                    expect($image->tags[$index])->toBeAnInstanceOf('stdClass');
                }
            }

        });

        it("embeds a hasManyThrough relationship using array hydration", function() {

            $hasManyThrough = Image::definition()->relation('tags');

            $images = Image::create([
                ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'],
                ['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'],
                ['id' => 5, 'gallery_id' => 2, 'title' => 'Unknown']
            ], ['type' => 'set', 'exists' => true]);

            $images = $images->data();

            expect(ImageTag::class)->toReceive('::all')->with([
                'conditions' => ['image_id' => [1, 2, 3, 4, 5]]
            ], ['return' => 'array']);

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 3, 5, 6]]
            ], ['return' => 'array']);

            $hasManyThrough->embed($images, ['fetchOptions' => ['return' => 'array']]);

            foreach ($images as $image) {
                foreach ($image['images_tags'] as $index => $image_tag) {
                    expect($image_tag['tag'])->toBe($image['tags'][$index]);
                    expect($image['tags'][$index])->toBeAn('array');
                }
            }

        });

    });

    describe("->get()", function() {

        it("lazy loads a belongsTo relation", function() {

            Stub::on(ImageTag::class)->method('::all', function($options = [], $fetchOptions = []) {
                $imagesTags =  ImageTag::create([
                    ['id' => 1, 'image_id' => 1, 'tag_id' => 1],
                    ['id' => 2, 'image_id' => 1, 'tag_id' => 3]
                ], ['type' => 'set', 'exists' => true]);
                return $imagesTags;
            });

            Stub::on(Tag::class)->method('::all', function($options = [], $fetchOptions = []) {
                $tags =  Tag::create([
                    ['id' => 1, 'name' => 'High Tech'],
                    ['id' => 3, 'name' => 'Computer']
                ], ['type' => 'set', 'exists' => true]);
                return $tags;
            });

            $image = Image::create(['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'], ['exists' => true]);

            expect(ImageTag::class)->toReceive('::all')->with([
                'conditions' => ['image_id' => 1]
            ], []);

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [1, 3]]
            ], []);

            expect(count($image->tags))->toBe(2);
            expect($image->tags[0]->data())->toBe(['id' => 1, 'name' => 'High Tech']);
            expect($image->tags[1]->data())->toBe(['id' => 3, 'name' => 'Computer']);

        });

    });

    describe("->broadcast()", function() {

        it("bails out on save since it's just an alias", function() {

            $hasManyThrough = Image::definition()->relation('tags');
            expect($hasManyThrough->broadcast(null))->toBe(true);

        });

    });

});