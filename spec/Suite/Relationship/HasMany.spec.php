<?php
namespace Chaos\ORM\Spec\Suite\Relationship;

use Chaos\ORM\ORMException;
use Chaos\ORM\Model;
use Chaos\ORM\Relationship;
use Chaos\ORM\Relationship\HasMany;
use Chaos\ORM\Conventions;

use Kahlan\Plugin\Stub;

use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;
use Chaos\ORM\Spec\Fixture\Model\Gallery;
use Chaos\ORM\Spec\Fixture\Model\Tag;

describe("HasMany", function() {

    beforeEach(function() {
        $this->conventions = new Conventions();
        $this->key = $this->conventions->apply('key');
    });

    afterEach(function() {
        Image::reset();
        ImageTag::reset();
        Gallery::reset();
    });

    describe("->__construct()", function() {

        it("creates a hasMany relationship", function() {

            $relation = new HasMany([
                'from' => Gallery::class,
                'to'   => Image::class
            ]);

            expect($relation->name())->toBe($this->conventions->apply('field', Image::class));

            $foreignKey = $this->conventions->apply('reference', Gallery::class);
            expect($relation->keys())->toBe([$this->key => $foreignKey]);

            expect($relation->from())->toBe(Gallery::class);
            expect($relation->to())->toBe(Image::class);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->conditions())->toBe([]);
            expect($relation->conventions())->toBeAnInstanceOf('Chaos\ORM\Conventions');

        });

        it("throws an exception if `'from'` is missing", function() {

            $closure = function() {
                $relation = new HasMany([
                    'to'   => Image::class
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'from'` option can't be empty."));

        });

        it("throws an exception if `'to'` is missing", function() {

            $closure = function() {
                $relation = new HasMany([
                    'from' => Gallery::class
                ]);
            };
            expect($closure)->toThrow(new ORMException("The relationship `'to'` option can't be empty."));

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
                ], ['type' => 'set', 'exists' => true]);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $images->data();
                }
                return $images;
            });

            Stub::on(Tag::class)->method('::all', function($options = [], $fetchOptions = []) {
                $tags =  Tag::create([
                    ['id' => 4, 'title' => 'Computer'],
                    ['id' => 5, 'title' => 'Science'],
                    ['id' => 6, 'title' => 'Landscape'],
                    ['id' => 7, 'title' => 'Sport']
                ], ['type' => 'set', 'exists' => true]);
                if (!empty($fetchOptions['return']) && $fetchOptions['return'] === 'array') {
                    return $tags->data();
                }
                return $tags;
            });
        });

        it("embeds a hasMany relationship", function() {

            $hasMany = Gallery::definition()->relation('images');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set', 'exists' => true]);

            expect(Image::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => [1, 2]]
            ], []);

            $galleries->embed(['images']);

            foreach ($galleries as $gallery) {
                expect(count($gallery->images))->not->toBe(0);
                foreach ($gallery->images as $image) {
                    expect($image->gallery_id)->toBe($gallery->id);
                }
            }

        });

        it("embeds a hasMany relationship using array hydration", function() {

            $hasMany = Gallery::definition()->relation('images');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery'],
                ['id' => 2, 'name' => 'Bar Gallery']
            ], ['type' => 'set', 'exists' => true]);

            $galleries = $galleries->data();

            expect(Image::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => [1, 2]]
            ], ['return' => 'array']);

            $hasMany->embed($galleries, ['fetchOptions' => ['return' => 'array']]);

            foreach ($galleries as $gallery) {
                expect(count($gallery['images']))->not->toBe(0);
                foreach ($gallery['images'] as $image) {
                    expect($gallery['id'])->toBe($image['gallery_id']);
                    expect($image)->toBeAn('array');
                }
            }

        });

        it("embeds a hasMany LINK_KEY_LIST relationships", function() {

            $hasMany = Gallery::definition()->relation('tags');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery', 'tag_ids' => [4, 5]],
                ['id' => 2, 'name' => 'Bar Gallery', 'tag_ids' => [6, 7]]
            ], ['type' => 'set', 'exists' => true]);

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [4, 5, 6, 7]]
            ], []);

            $galleries->embed(['tags']);

            foreach ($galleries as $gallery) {
                expect(count($gallery->tags))->not->toBe(0);
                foreach ($gallery->tags as $tag) {
                    expect($gallery->tag_ids->data())->toContain($tag->id());
                }
            }

        });

        it("embeds a hasMany LINK_KEY_LIST relationships using array hydration", function() {

            $hasMany = Gallery::definition()->relation('tags');

            $galleries = Gallery::create([
                ['id' => 1, 'name' => 'Foo Gallery', 'tag_ids' => [4, 5]],
                ['id' => 2, 'name' => 'Bar Gallery', 'tag_ids' => [6, 7]]
            ], ['type' => 'set', 'exists' => true]);

            $galleries = $galleries->data();

            expect(Tag::class)->toReceive('::all')->with([
                'conditions' => ['id' => [4, 5, 6, 7]]
            ], []);

            $hasMany->embed($galleries);

            foreach ($galleries as $gallery) {
                expect(count($gallery['tags']))->not->toBe(0);
                foreach ($gallery['tags'] as $tag) {
                    expect($gallery['tag_ids'])->toContain($tag['id']);
                }
            }

        });

    });

    describe("->get()", function() {

        it("throws an exception when a lazy load is necessary", function() {

            $closure = function() {
                $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery' ], ['exists' => true]);
                $gallery->get('images');
            };

            expect($closure)->toThrow(new ORMException("The relation `'images'` is an external relation, use `fetch()` to lazy load its data."));

        });

    });

    describe("->fetch()", function() {

        it("returns an empty collection when no hasMany relation exists", function() {

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([], ['type' => 'set', 'exists' => true]);
                return $images;
            });

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);

            expect(Image::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => 1]
            ], []);

            expect($gallery->images->count())->toBe(0);

        });

        it("lazy loads a hasMany relation", function() {

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([
                    ['id' => 1, 'gallery_id' => 1, 'title' => 'Amiga 1200'],
                    ['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'],
                    ['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas']
                ], ['type' => 'set', 'exists' => true]);
                return $images;
            });

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);

            expect(Image::class)->toReceive('::all')->with([
                'conditions' => ['gallery_id' => 1]
            ], []);

            foreach ($gallery->images as $image) {
                expect($image->gallery_id)->toBe($gallery->id);
            }

        });

    });

    describe("->save()", function() {

        it("bails out if no relation data hasn't been setted", function() {

            $hasMany = Gallery::definition()->relation('images');
            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            expect($hasMany->save($gallery))->toBe(true);

        });

        it("saves a hasMany relationship", function() {

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) {
                $images =  Image::create([], ['type' => 'set']);
                return $images;
            });

            $hasMany = Gallery::definition()->relation('images');

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

        it("assures removed association to be unsetted", function() {

            $toUnset = Image::create(['id' => 2, 'gallery_id' => 1, 'title' => 'Srinivasa Ramanujan'], ['exists' => true]);
            $toKeep = Image::create(['id' => 3, 'gallery_id' => 1, 'title' => 'Las Vegas'], ['exists' => true]);

            Stub::on(Image::class)->method('::all', function($options = [], $fetchOptions = []) use ($toUnset, $toKeep){
                $images =  Image::create([
                    $toUnset,
                    $toKeep
                ], ['type' => 'set']);
                return $images;
            });

            $hasMany = Gallery::definition()->relation('images');

            $gallery = Gallery::create(['id' => 1, 'name' => 'Foo Gallery'], ['exists' => true]);
            $gallery->images = [['title' => 'Amiga 1200'], $toKeep];

            Stub::on($gallery->images[0])->method('save', function() use ($gallery) {
                $gallery->images[0]->id = 1;
                return true;
            });

            Stub::on($toKeep)->method('save', function() { return true; });
            Stub::on($toUnset)->method('save', function() use ($toUnset) {return true;});

            expect($gallery->images[0])->toReceive('save');
            expect($toKeep)->toReceive('save');
            expect($toUnset)->toReceive('save');
            expect($hasMany->save($gallery))->toBe(true);
            expect($toUnset->exists())->toBe(true);
            expect($toUnset->gallery_id)->toBe(null);
            expect($gallery->images[0]->gallery_id)->toBe($gallery->id);

        });

        it("assures removed associative entity to be deleted", function() {

            $toDelete = ImageTag::create(['id' => 5, 'image_id' => 4, 'tag_id' => 6], ['exists' => true]);
            $toKeep = ImageTag::create(['id' => 6, 'image_id' => 4, 'tag_id' => 3], ['exists' => true]);

            Stub::on(ImageTag::class)->method('::all', function($options = [], $fetchOptions = []) use ($toDelete, $toKeep){
                $images =  ImageTag::create([
                    $toDelete,
                    $toKeep
                ], ['type' => 'set']);
                return $images;
            });

            $hasMany = Image::definition()->relation('images_tags');

            $image = Image::create(['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley'], ['exists' => true]);
            $image->images_tags = [['tag_id' => 1], $toKeep];

            Stub::on($image->images_tags[0])->method('save', function() use ($image) {
                $image->images_tags[0]->id = 7;
                return true;
            });

            $schema = ImageTag::definition();

            Stub::on($toKeep)->method('save', function() { return true; });
            Stub::on($schema)->method('remove', function() { return true; });

            expect($image->images_tags[0])->toReceive('save');
            expect($toKeep)->toReceive('save');
            expect($schema)->toReceive('remove')->with(['id' => 5]);
            expect($hasMany->save($image))->toBe(true);
            expect($toDelete->exists())->toBe(false);
            expect($image->images_tags[0]->image_id)->toBe($image->id);

        });

        it("assures removed associative entity to be deleted according to the defined scope", function() {

            $schema = Image::definition();
            $schema->hasMany('images_tags', ImageTag::class, [
                'keys' => ['id' => 'tag_id'],
                'conditions' => ['scope' => 1]
            ]);

            $result = [];

            Stub::on(ImageTag::class)->method('::all', function($options = [], $fetchOptions = []) use (&$result) {
                $result = $options;
                return ImageTag::create([], ['type' => 'set']);
            });

            $relation = $schema->relation('images_tags');

            $relation->save(Image::create(['id' => 4, 'gallery_id' => 2, 'title' => 'Silicon Valley', 'images_tags' => []], ['exists' => true]));

            expect($result['conditions'])->toBe([
                'tag_id' => 4,
                'scope' => 1
            ]);
        });

    });

});
