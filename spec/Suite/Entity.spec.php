<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\ORM\ORMException;
use Chaos\ORM\Model;
use Chaos\ORM\Schema;
use Chaos\ORM\Collection\Collection;
use Chaos\ORM\Collection\Through;

use Kahlan\Plugin\Double;

use Chaos\ORM\Spec\Fixture\Model\Gallery;
use Chaos\ORM\Spec\Fixture\Model\GalleryDetail;
use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;
use Chaos\ORM\Spec\Fixture\Model\Tag;

describe("Entity", function() {

    beforeEach(function() {
        $this->model = Double::classname(['extends' => Model::class]);
        $model = $this->model;
        $schema = $model::definition();
        $schema->column('id', ['type' => 'serial']);
        $schema->lock(false);
    });

    describe("->__construct()", function() {

        it("loads the data", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $model = $this->model;
            $entity = $model::create([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]);
            expect($entity->title)->toBe('Hello');
            expect($entity->body)->toBe('World');
            expect($entity->created)->toBe($date);
            expect($entity)->toHaveLength(3);

        });

        it("throws an exception if exists is `null` but no record actually exists", function() {

            $model = $this->model;
            allow($model)->toReceive('::load')->andRun(function() { return; });

            $closure = function() {
                $model = $this->model;
                $entity = $model::create([
                    'id'    => 1,
                    'title' => 'Good Bye',
                    'body'  => 'Folks'
                ], ['exists' => null]);
            };

            expect($closure)->toThrow(new ORMException("The entity id:`1` doesn't exists."));

        });

    });

    describe("->self()", function() {

        it("returns the entity class name", function() {

            $model = $this->model;
            $entity = $model::create();
            expect($entity->self())->toBe($model);

        });

    });

    describe("->exists()", function() {

        it("returns the exists value", function() {

            $model = $this->model;
            $entity = $model::create(['id' => 123], ['exists' => true]);
            expect($entity->exists())->toBe(true);

        });

    });

    describe("->id()", function() {

        it("returns the entity's primary key value", function() {

            $model = $this->model;
            $entity = $model::create([
                'id'    => 123,
                'title' => 'Hello',
                'body'  => 'World'
            ]);
            expect($entity->id())->toBe(123);

        });

        it("throws an exception if the schema has no primary key defined", function() {

            $schema = new Schema(['key' => null]);
            $schema->lock(false);

            $model = $this->model;
            $model::definition($schema);

            $closure = function() {
                $model = $this->model;
                $entity = $model::create([
                    'id'    => 123,
                    'title' => 'Hello',
                    'body'  => 'World'
                ]);
                $entity->id();
            };
            expect($closure)->toThrow(new ORMException("No primary key has been defined for `{$model}`'s schema."));
            $model::reset();

        });

        it("throws an exception when trying to update an entity with no ID data", function() {

            $closure = function() {
                $model = $this->model;
                $entity = $model::create([], ['exists' => true]);
                $entity->id();
            };
            expect($closure)->toThrow(new ORMException("Existing entities must have a valid ID."));

        });

    });

    describe("->sync()", function() {

        it("syncs an entity to its persisted value", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity->modified = 'modified';

            expect($entity->exists())->toBe(false);
            expect($entity->id())->toBe(null);
            expect($entity->modified('modified'))->toBe(true);

            $entity->sync(123, ['added' => 'added'], ['exists' => true]);

            expect($entity->exists())->toBe(true);
            expect($entity->id())->toBe(123);
            expect($entity->modified('modified'))->toBe(false);
            expect($entity->modified('added'))->toBe(false);
            expect($entity->added)->toBe('added');

        });

        context("when there's no primary key", function() {

            it("syncs an entity to its persisted value", function() {

                $model = $this->model;
                $entity = $model::create();
                $entity->modified = 'modified';

                expect($entity->exists())->toBe(false);
                expect($entity->id())->toBe(null);
                expect($entity->modified('modified'))->toBe(true);

                $entity->sync(null, ['added' => 'added'], ['exists' => true]);

                expect($entity->exists())->toBe(true);
                expect($entity->id())->toBe(null);
                expect($entity->modified('modified'))->toBe(false);
                expect($entity->modified('added'))->toBe(false);
                expect($entity->added)->toBe('added');

            });

        });

    });

    describe("->get()/->set()", function() {

        afterEach(function() {
            Image::reset();
        });

        it("sets values", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $model = $this->model;
            $entity = $model::create();
            expect($entity->set('title', 'Hello'))->toBe($entity);
            expect($entity->set('body', 'World'))->toBe($entity);
            expect($entity->set('created', $date))->toBe($entity);

            expect($entity->title)->toBe('Hello');
            expect($entity->body)->toBe('World');
            expect($entity->created)->toBe($date);
            expect($entity)->toHaveLength(3);

        });

        it("sets an array of values", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $model = $this->model;
            $entity = $model::create();
            expect($entity->set([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]))->toBe($entity);
            expect($entity->title)->toBe('Hello');
            expect($entity->body)->toBe('World');
            expect($entity->created)->toBe($date);
            expect($entity)->toHaveLength(3);

        });

        it("sets nested arbitraty value in cascade when locked is `false`", function() {

            Image::definition()->lock(false);

            $image = Image::create();
            $image->set('a.nested.value', 'hello');

            expect($image->data())->toEqual([
                'a' => [
                    'nested' => [
                        'value' => 'hello'
                    ]
                ]
            ]);

        });

        it("sets a single belongsTo relation", function() {

            $image = Image::create();
            $image->set('gallery', [ 'id' => '1', 'name' => 'MyGallery' ]);

            expect($image->get('gallery') instanceof Gallery)->toBe(true);
            expect($image->get('gallery')->data())->toEqual([ 'id' => 1, 'name' => 'MyGallery' ]);

        });

        it("sets a single hasMany relation", function() {

            $image = Image::create();
            $image->set('images_tags.0', [ 'id' => '1', 'image_id' => '1', 'tag_id' => '1' ]);

            expect($image->get('images_tags') instanceof Collection)->toBe(true);
            expect($image->get('images_tags.0') instanceof ImageTag)->toBe(true);
            expect($image->get('images_tags.0')->data())->toEqual([ 'id' => 1, 'image_id' => 1, 'tag_id' => 1 ]);

        });

        it("sets a hasMany array", function() {

            $image = Image::create();
            $image->set('images_tags', [
                [
                    'id' => '1',
                    'image_id' => '1',
                    'tag_id' => '1'
                ],
                [
                    'id' => '2',
                    'image_id' => '1',
                    'tag_id' => '2'
                ]
            ]);
            expect($image->get('images_tags') instanceof Collection)->toBe(true);
            expect($image->get('images_tags.0')->data())->toEqual([ 'id' => 1, 'image_id' => 1, 'tag_id' => 1 ]);
            expect($image->get('images_tags.1')->data())->toEqual([ 'id' => 2, 'image_id' => 1, 'tag_id' => 2 ]);

        });

        it("sets a single hasManyThrough relation", function() {

            $image = Image::create();

            $image->set('tags.0', [ 'id' => '1', 'name' => 'landscape' ]);

            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags.0') instanceof Tag)->toBe(true);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);

        });

        it("sets a hasManyThrough array", function() {

            $image = Image::create();
            $image->set('tags', [
                [
                    'id' => '1',
                    'name' => 'landscape'
                ],
                [
                    'id' => '2',
                    'name' => 'mountain'
                ]
            ]);
            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);
            expect($image->get('tags.1')->data())->toEqual([ 'id' => 2, 'name' => 'mountain' ]);

        });

        it("resets a hasManyThrough array using a plain array", function() {

            $image = Image::create();
            $tags = [
                [
                    'id' => '1',
                    'name' => 'landscape'
                ],
                [
                    'id' => '2',
                    'name' => 'mountain'
                ]
            ];
            $image->set('tags', $tags);
            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);
            expect($image->get('tags.1')->data())->toEqual([ 'id' => 2, 'name' => 'mountain' ]);

            $image->set('tags', $tags);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);
            expect($image->get('tags.1')->data())->toEqual([ 'id' => 2, 'name' => 'mountain' ]);

        });

        it("resets a hasManyThrough array using a Through collection", function() {

            $image = Image::create();
            $tags = [
                [
                    'id' => '1',
                    'name' => 'landscape'
                ],
                [
                    'id' => '2',
                    'name' => 'mountain'
                ]
            ];

            $image->set('tags', $tags);
            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);
            expect($image->get('tags.1')->data())->toEqual([ 'id' => 2, 'name' => 'mountain' ]);

            $tags = $image->get('tags');
            $image->set('tags', $tags);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual([ 'id' => 1, 'name' => 'landscape' ]);
            expect($image->get('tags.1')->data())->toEqual([ 'id' => 2, 'name' => 'mountain' ]);
            expect($image->get('tags'))->not->toBe($tags);

        });

        it("throws an exception when trying to set nested arbitraty value in cascade when locked is `true`", function() {

            $closure = function() {
                $image = Image::create();
                $image->set('a.nested.value', 'hello');
            };

            expect($closure)->toThrow(new ORMException('Missing schema definition for field: `a`.'));

        });

        it("sets a value using a virtual field", function() {

            $model = $this->model;
            $schema = $model::definition();
            $schema->column('hello_boy', [
                'setter' => function($entity, $data, $name) {
                    return 'Hi ' . $data;
                }
            ]);

            $entity = $model::create();

            $entity->hello_boy = 'boy';
            expect($entity->hello_boy)->toBe('Hi boy');

        });

        it("gets a value using a virtual field", function() {

            $model = $this->model;
            $schema = $model::definition();
            $schema->column('hello_boy', [
                'getter' => function($entity, $data, $name) {
                    return 'Hi Boy!';
                }
            ]);

            $entity = $model::create();
            expect($entity->hello_boy)->toBe('Hi Boy!');

        });

        context("when a model is defined", function() {

            beforeEach(function() {
                $this->model = Double::classname(['extends' => $this->model]);
            });

            it("autoboxes setted data", function() {

                $model = $this->model;
                $childEntity = Double::classname(['extends' => $this->model]);
                $childEntity::definition()->lock(false);

                $schema = new Schema(['class' => $model]);
                $schema->column('child', [
                    'type'  => 'object',
                    'class' => $childEntity
                ]);

                $model::definition($schema);

                $entity = $model::create();

                $entity['child'] = [
                    'id'      => 1,
                    'title'   => 'child record',
                    'enabled' => true
                ];
                $child = $entity['child'];

                expect($child)->toBeAnInstanceOf($childEntity);
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe(null);

            });

        });

    });

    describe("->validates()", function() {

        beforeEach(function() {
            $validator = Gallery::validator();
            $validator->rule('name', 'not:empty');

            $validator = Image::validator();
            $validator->rule('name', 'not:empty');
        });

        afterEach(function() {
            Gallery::reset();
            Image::reset();
        });

        it("validates an entity", function() {

            $gallery = Gallery::create();
            expect($gallery->validates())->toBe(false);
            expect($gallery->errors())->toBe(['name' => ['is required']]);

            $gallery->name = '';
            expect($gallery->validates())->toBe(false);
            expect($gallery->errors())->toBe(['name' => ['must not be a empty']]);

            $gallery->name = 'new gallery';
            expect($gallery->validates())->toBe(true);
            expect($gallery->errors())->toBe([]);

        });

        it("validates a belongsTo nested entity", function() {

            $image = Image::create();
            $image->gallery = Gallery::create();
            expect($image->validates())->toBe(false);
            expect($image->errors())->toBe([
                'name' => ['is required'],
                'gallery' => [
                    'name' => ['is required']
                ]
            ]);

            $image->name = 'new image';
            expect($image->validates())->toBe(false);
            expect($image->errors())->toBe([
                'gallery' => [
                    'name' => ['is required']
                ]
            ]);

            $image->gallery->name = 'new gallery';
            expect($image->validates())->toBe(true);
            expect($image->errors())->toBe([]);

        });

        it("validates a hasMany nested entities", function() {

            $gallery = Gallery::create();
            $gallery->images[] = Image::create();
            $gallery->images[] = Image::create();

            expect($gallery->validates())->toBe(false);
            expect($gallery->errors())->toBe([
                'name'   => ['is required'],
                'images' => [
                    ['name' => ['is required']],
                    ['name' => ['is required']]
                ]
            ]);

            $gallery->name = '';
            $gallery->images[0]->name = '';
            $gallery->images[1]->name = '';
            expect($gallery->validates())->toBe(false);
            expect($gallery->errors())->toBe([
                'name'   => ['must not be a empty'],
                'images' => [
                    ['name' => ['must not be a empty']],
                    ['name' => ['must not be a empty']]
                ]
            ]);

            $gallery->name = 'new gallery';
            $gallery->images[0]->name = 'image1';
            $gallery->images[1]->name = '';
            expect($gallery->validates())->toBe(false);
            expect($gallery->errors())->toBe([
                'images' => [
                    [],
                    ['name' => ['must not be a empty']]
                ]
            ]);

            $gallery->name = 'new gallery';
            $gallery->images[0]->name = 'image1';
            $gallery->images[1]->name = 'image2';
            expect($gallery->validates())->toBe(true);
            expect($gallery->errors())->toBe([]);

        });

        it("passes entity instances to validator handlers", function() {

            $actual = null;

            $validator = Gallery::validator();

            $validator->set('customValidationRule', function($value, $options = [], &$params = []) use (&$actual) {
                $actual = $options;
            });

            $validator->rule('name', ['customValidationRule']);

            $gallery = Gallery::create(['name' => 'test']);
            expect($gallery->validates())->toBe(false);
            expect($actual['entity'])->toBe($gallery);

        });

    });

    describe("->invalidate()", function() {

        it("invalidates an field", function() {

            $image = Image::create();

            expect($image->errored())->toBe(false);
            expect($image->errored('name'))->toBe(false);

            expect($image->invalidate('name', 'is required'))->toBe($image);

            expect($image->errored())->toBe(true);
            expect($image->errored('name'))->toBe(true);
            expect($image->error('name'))->toBe('is required');
            expect($image->errors())->toBe([
                'name'   => ['is required']
            ]);


        });

        it("invalidates multiple fields", function() {

            $image = Image::create();

            expect($image->errored())->toBe(false);
            expect($image->errored('title'))->toBe(false);

            expect($image->invalidate([
                'name'  => 'is required',
                'title' => ['error1', 'error2']
            ]))->toBe($image);

            expect($image->errored())->toBe(true);
            expect($image->errored('title'))->toBe(true);
            expect($image->error('title'))->toBe('error1');
            expect($image->errors())->toBe([
                'name'  => ['is required'],
                'title' => ['error1', 'error2']
            ]);

        });

    });

    describe("->broadcast()", function() {

        afterEach(function() {

            Image::reset();
            Gallery::reset();

        });

        it("validates by default", function() {

            $image = Image::create([]);
            Image::validator()->rule('name', 'not:empty');

            expect($image->broadcast())->toBe(false);
            expect($image->exists())->toBe(false);

        });

        it("validates direct relationships by default", function() {

            Gallery::validator()->rule('name', 'not:empty');

            $image = Image::create([
                'name'    => 'amiga_1200.jpg',
                'title'   => 'Amiga 1200',
                'gallery' => []
            ]);
            expect($image->broadcast())->toBe(false);
            expect($image->exists())->toBe(false);

        });

    });

    describe("->hierarchy()", function() {

        it("returns all included relations and sub-relations with non empty data", function() {

            $gallery = Gallery::create(['name' => 'Gallery1']);

            $gallery->detail = ['description' => 'Tech'];

            $image = Image::create([
                'title' => 'Amiga 1200'
            ]);
            $image->tags[] = ['name' => 'Computer'];
            $image->tags[] = ['name' => 'Science'];

            $image->gallery = $gallery;

            $gallery->images[] = $image;

            expect($gallery->hierarchy())->toBe([
                'detail',
                'images_tags.tag',
                'images.tags'
            ]);

        });

    });

    describe("->to('array')", function() {

        it("exports data using `'array'` formatter handlers", function() {

            $model = $this->model;

            $schema = $model::definition();
            $schema->column('created', ['type' => 'date']);

            $entity = $model::create([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => new DateTime('2014-10-26 00:25:15')
            ]);

            expect($entity->data())->toBe([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => '2014-10-26'
            ]);

        });

        it("supports recursive structures", function() {

            $data = [
                'name'  => 'amiga_1200.jpg',
                'title' => 'Amiga 1200',
                'tags'  => [
                    ['name' => 'tag1']
                ]
            ];

            $image = Image::create($data);
            foreach ($image->tags as $tag) {
                $tag->images[] = $image;
            }
            expect($image->data())->toBe([
                'name' => 'amiga_1200.jpg',
                'title' => 'Amiga 1200',
                'images_tags' => [
                    ['tag' => ['name' => 'tag1']]
                ],
                'tags' => [
                    ['name' => 'tag1']
                ]
            ]);

        });

        it("supports the `'embed'` option", function() {

            $image = Image::create([
                'title' => 'Amiga 1200'
            ]);
            $image->tags[] = ['name' => 'Computer'];
            $image->tags[] = ['name' => 'Science'];

            $image->gallery = ['name' => 'Gallery 1'];

            expect($image->to('array'))->toBe([
                'title' => 'Amiga 1200',
                'gallery' => ['name' => 'Gallery 1'],
                'images_tags' => [
                    ['tag' => ['name' => 'Computer']],
                    ['tag' => ['name' => 'Science']]
                ],
                'tags'  => [
                    ['name' => 'Computer'],
                    ['name' => 'Science']
                ]
            ]);

            expect($image->to('array', ['embed' => ['gallery']]))->toBe([
                'title'   => 'Amiga 1200',
                'gallery' => ['name' => 'Gallery 1']
            ]);

            expect($image->to('array', ['embed' => false]))->toBe([
                'title' => 'Amiga 1200'
            ]);
        });

    });

    describe("->__toString()", function() {

        it("returns the title field", function() {

            $data = [
                'id'    => 1,
                'title' => 'test record'
            ];

            $model = $this->model;
            $entity = $model::create($data);
            expect((string) $entity)->toBe('test record');

        });

    });

});