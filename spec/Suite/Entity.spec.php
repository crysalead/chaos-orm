<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\ORM\ORMException;
use Chaos\ORM\Document;
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

        it("sets `exists` value to `null` for children", function() {

            $image = Image::create([
                'id'      => 123,
                'name'    => 'amiga_1200.jpg',
                'title'   => 'Amiga 1200',
                'gallery' => ['id' => 456, 'name' => 'MyGallery']
            ], ['exists' => true]);

            expect($image->exists())->toBe(true);

            $closure = function() use ($image) {
                $image->gallery->exists();
            };

            expect($closure)->toThrow(new ORMException("No persitance information is available for this entity use `sync()` to get an accurate existence value."));

        });

        context("when unicity is enabled", function() {

            it("replace old references", function() {

                $model = $this->model;
                $model::unicity(true);
                $data = ['id' => '1', 'title' => 'Amiga 1200'];
                $entity = $model::create(['id' => '1', 'title' => 'Amiga 1200'], ['exists' => true]);
                $entity = $model::create(['id' => '1', 'title' => 'Amiga 1260'], ['exists' => true]);

                $closure = function() use ($data) {
                    $model = $this->model;
                    new $model([
                        'data' => ['id' => '1', 'title' => 'Amiga 1260'],
                        'exists' => true
                    ]);
                };

                $schema = $model::definition();
                $source = $schema->source();
                expect($closure)->toThrow(new ORMException("Trying to create a duplicate of `{$source}` ID `1` which is not allowed when unicity is enabled."));

                $model::reset();

            });

        });

    });

    describe("::create()", function() {

        it("eagerly populates default values by default", function() {

            $model = $this->model;
            $model::definition()->column('hello', ['type' => 'string', 'default' => 'world']);
            $entity = $model::create();

            expect($entity->has('hello'))->toBe(true);
            expect($entity->get('hello'))->toBe('world');

        });

        it("lazily populates default values when the `'defaults'` option is set to false", function() {

            $model = $this->model;
            $model::definition()->column('hello', ['type' => 'string', 'default' => 'world']);
            $entity = $model::create([], ['defaults' => false]);

            expect($entity->has('hello'))->toBe(false);
            expect($entity->get('hello'))->toBe('world');

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

    describe("->amend()", function() {

        it("amends an entity to its persisted value", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity->modified = 'modified';

            expect($entity->exists())->toBe(false);
            expect($entity->id())->toBe(null);
            expect($entity->modified('modified'))->toBe(true);

            $entity->amend(['id' => 123, 'added' => 'added'], ['exists' => true]);

            expect($entity->exists())->toBe(true);
            expect($entity->id())->toBe(123);
            expect($entity->modified('modified'))->toBe(false);
            expect($entity->modified('added'))->toBe(false);
            expect($entity->added)->toBe('added');

        });

        it("amends all associated data", function() {

            $image = Image::create([
                'name'  => 'amiga_1200.jpg',
                'title' => 'Amiga 1200'
            ]);

            expect($image->exists())->toBe(false);

            $image->amend([
                'id' => 123,
                'gallery' => ['id' => 456, 'name' => 'MyGallery']
            ], ['exists' => 'all']);

            expect($image->id())->toBe(123);
            expect($image->exists())->toBe(true);
            expect($image->gallery->exists())->toBe(true);

        });

        context("when there's no primary key", function() {

            it("amends an entity to its persisted value", function() {

                $model = $this->model;
                $entity = $model::create();
                $entity->modified = 'modified';

                expect($entity->exists())->toBe(false);
                expect($entity->id())->toBe(null);
                expect($entity->modified('modified'))->toBe(true);

                $entity->amend(['added' => 'added'], ['exists' => true]);

                expect($entity->exists())->toBe(true);
                expect($entity->id())->toBe(null);
                expect($entity->modified('modified'))->toBe(false);
                expect($entity->modified('added'))->toBe(false);
                expect($entity->added)->toBe('added');

            });

        });

        context("when unicity is enabled", function() {

            it("stores the entity in the shard when the entity has been persisted", function() {

                $model = $this->model;
                $model::unicity(true);
                $shard = $model::shard();

                $data = ['id' => '1', 'title' => 'Amiga 1200'];
                $entity = $model::create($data);

                expect($shard->has($entity->id()))->toBe(false);

                $entity->amend(['name' => 'file.jpg'], ['exists' => true]);

                expect($shard->has($entity->id()))->toBe(true);
                expect($shard->count())->toBe(1);

                expect($entity->name)->toBe('file.jpg');

                $model::reset();

            });

            it("removes the entity from the shard when the entity has been deleted", function() {

                $model = $this->model;
                $model::unicity(true);
                $shard = $model::shard();

                $data = ['id' => '1', 'title' => 'Amiga 1200'];
                $entity = $model::create($data, ['exists' => true]);

                expect($shard->has($entity->id()))->toBe(true);
                expect($shard->count())->toBe(1);

                $entity->amend(['name' => 'file.jpg'], ['exists' => false]);

                expect($shard->has($entity->id()))->toBe(false);

                expect($entity->name)->toBe('file.jpg');

                $model::reset();

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
                'gallery_id' => null,
                'a' => [
                    'nested' => [
                        'value' => 'hello'
                    ]
                ]
            ]);

            expect($image)->toBeAnInstanceOf(Image::class);
            expect($image->a)->toBeAnInstanceOf(Document::class);
            expect($image->a->nested)->toBeAnInstanceOf(Document::class);

        });

        it("sets a single belongsTo relation", function() {

            $image = Image::create();
            $image->set('gallery', [ 'id' => '1', 'name' => 'MyGallery' ]);

            expect($image->get('gallery_id'))->toBe(1);
            expect($image->get('gallery') instanceof Gallery)->toBe(true);
            expect($image->get('gallery')->data())->toEqual([ 'id' => 1, 'name' => 'MyGallery', 'tag_ids' => []]);

        });

        it("clears a single belongsTo relation when using a `null/undefined` value", function() {

            $image = Image::create();
            $image->set('gallery', [ 'id' => '1', 'name' => 'MyGallery' ]);
            $image->set('gallery', null);

            expect($image->get('gallery_id'))->toBe(null);
            expect($image->get('gallery'))->toBe(null);

        });

        it("sets a single hasMany relation", function() {

            $image = Image::create();
            $image->set('images_tags.0', [ 'id' => '1', 'image_id' => '1', 'tag_id' => '1' ]);

            expect($image->get('images_tags') instanceof Collection)->toBe(true);
            expect($image->get('images_tags.0') instanceof ImageTag)->toBe(true);
            expect($image->get('images_tags.0')->data())->toEqual([ 'id' => 1, 'image_id' => 1, 'tag_id' => 1 ]);

        });

        it("clears a single hasMany relation when using a `null/undefined` value", function() {

            $image = Image::create();
            $image->set('images_tags.0', [ 'id' => '1', 'image_id' => '1', 'tag_id' => '1' ]);
            $image->set('images_tags', null);

            expect($image->get('images_tags') instanceof Collection)->toBe(true);
            expect($image->get('images_tags')->count())->toBe(0);

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

        });

        it("amends the pivot collection when some different hasManyThrough data is setted for the second time", function() {

            $image = Image::create();
            $image->set('tags', [
              [
                'id' => '1',
                'name' => 'landscape'
              ]
            ]);

            expect($image->get('images_tags')->modified())->toBe(false);

            $image->set('tags', [
              [
                'id' => '2',
                'name' => 'mountain'
              ]
            ]);

            expect($image->get('images_tags')->modified())->toBe(true);

        });

        it("doesn't amend the pivot collection when some hasManyThrough data is setted for the second time", function() {

            $image = Image::create();
            $image->set('tags', [
              [
                'id' => '1',
                'name' => 'landscape'
              ]
            ]);

            expect($image->get('images_tags')->modified())->toBe(false);

            $image->set('tags', [
              [
                'id' => '1',
                'name' => 'landscape'
              ]
            ]);

            expect($image->get('images_tags')->modified())->toBe(false);

        });

        it("keeps existing value on resets with hasManyThrough data", function() {

            $image = Image::create([
                'id' => 1,
                'name' => 'landscape',
                'images_tags' => [
                    [
                        'id' => '1',
                        'image_id' => '1',
                        'tag_id' => '1',
                        'tag' => [
                            'id' => '1',
                            'name' => 'landscape'
                        ]
                    ],
                    [
                        'id' => '2',
                        'image_id' => '1',
                        'tag_id' => '2',
                        'tag' => [
                            'id' => '2',
                            'name' => 'mountain'
                        ]
                    ]
                ]
            ], ['exists' => 'all']);

            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual(['id' => 1, 'name' => 'landscape']);
            expect($image->get('tags.0')->exists())->toBe(true);
            expect($image->get('tags.1')->data())->toEqual(['id' => 2, 'name' => 'mountain']);
            expect($image->get('tags.1')->exists())->toBe(true);
            expect($image->get('images_tags')->modified())->toBe(false);

            $image->set('tags', $image->get('tags')->get());
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual(['id' => 1, 'name' => 'landscape']);
            expect($image->get('tags.0')->exists())->toBe(true);
            expect($image->get('tags.1')->data())->toEqual(['id' => 2, 'name' => 'mountain']);
            expect($image->get('tags.1')->exists())->toBe(true);
            expect($image->get('images_tags')->modified())->toBe(false);

        });

        it("keeps existing pivot id when possible", function() {

            $image = Image::create([
                'id' => 1,
                'name' => 'landscape',
                'images_tags' => [
                    [
                        'id' => '1',
                        'image_id' => '1',
                        'tag_id' => '1',
                        'tag' => [
                            'id' => '1',
                            'name' => 'landscape'
                        ]
                    ],
                    [
                        'id' => '2',
                        'image_id' => '1',
                        'tag_id' => '2',
                        'tag' => [
                            'id' => '2',
                            'name' => 'mountain'
                        ]
                    ]
                ]
            ], ['exists' => 'all']);

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
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual(['id' => 1, 'name' => 'landscape']);
            expect($image->get('tags.0')->exists())->toBe(true);
            expect($image->get('tags.1')->data())->toEqual(['id' => 2, 'name' => 'mountain']);
            expect($image->get('tags.1')->exists())->toBe(true);
            expect($image->modified())->toBe(false);
            expect($image->get('images_tags')->modified())->toBe(false);

            $image->set('tags', [
              [
                'id' => '2',
                'name' => 'landscape'
              ],
              [
                'id' => '3',
                'name' => 'sea'
              ]
            ]);
            expect($image->get('tags')->count())->toBe(2);
            expect($image->get('tags.0')->data())->toEqual(['id' => 2, 'name' => 'landscape']);
            expect($image->get('tags.0')->exists())->toBe(true);
            expect($image->get('tags.1')->data())->toEqual(['id' => 3, 'name' => 'sea']);
            expect($image->modified(['embed' => 'images_tags']))->toBe(true);
            expect($image->get('images_tags')->modified())->toBe(true);

        });

        it("gets the hasManyThrough array from hasMany/belongsTo data", function() {

            $image = Image::create(['id' => 1], ['exists' => true]);
            $image->set('images_tags', [
                [
                    'id' => '1',
                    'image_id' => '1',
                    'tag_id' => '1',
                    'tag' => [
                        'id' => '1',
                        'name' => 'landscape'
                    ]
                ],
                [
                    'id' => '2',
                    'image_id' => '1',
                    'tag_id' => '2',
                    'tag' => [
                        'id' => '2',
                        'name' => 'mountain'
                    ]
                ]
            ]);

            expect($image->get('tags') instanceof Through)->toBe(true);
            expect($image->get('tags.0')->data())->toBe([ 'id' => 1, 'name' => 'landscape']);
            expect($image->get('tags.1')->data())->toBe([ 'id' => 2, 'name' => 'mountain']);

        });

        it("clears the pivot collection when an empty array is setted as hasManyThrough data", function() {

            $image = Image::create();
            $image->set('tags', [
              [
                'id' => '1',
                'name' => 'landscape'
              ]
            ]);

            expect($image->get('images_tags')->modified())->toBe(false);

            $image->set('tags', []);

            expect($image->get('images_tags')->count())->toBe(0);
            expect($image->get('images_tags')->modified())->toBe(true);

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
                $this->model = $model = Double::classname(['extends' => Model::class]);
                $schema = $model::definition();
                $schema->column('id', ['type' => 'serial']);
                $schema->lock(false);
            });

            it("autoboxes object columns", function() {

                $model = $this->model;

                $schema = $model::definition();
                $schema->column('child', [
                    'type'  => 'object'
                ]);

                $entity = $model::create();

                $entity['child'] = [
                    'id'      => 1,
                    'title'   => 'child record',
                    'enabled' => true
                ];
                $child = $entity['child'];

                expect(get_class($child))->toBe(Document::class);
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe('child');

            });

            it("autoboxes object columns with a custom model name", function() {

                $model = $this->model;
                $MyChildModel = Double::classname(['extends' => $this->model]);
                $MyChildModel::definition()->lock(false);

                $schema = new Schema(['class' => $model]);
                $schema->column('child', [
                    'type'  => 'object',
                    'class' => $MyChildModel
                ]);
                $schema->lock(false);

                $model::definition($schema);

                $entity = $model::create();

                $entity['child'] = [
                    'id'      => 1,
                    'title'   => 'child record',
                    'enabled' => true
                ];
                $child = $entity['child'];

                expect($child)->toBeAnInstanceOf($MyChildModel);
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe(null);

            });

            it("lazy applies object columns schema to support single table inheritance", function() {

                $model = $this->model;
                $MyChildModel = Double::classname(['extends' => $this->model]);
                $MyChildModel::definition()->column('id', ['type' => 'serial']);

                allow($MyChildModel)->toReceive('::create')->andRun(function($data, $options) use ($model) {
                    $options['class'] = Document::class;
                    return $model::create($data, $options);
                });

                $schema = new Schema(['class' => $model]);
                $schema->column('child', [
                    'type'  => 'object',
                    'class' => $MyChildModel
                ]);
                $schema->lock(false);

                $model::definition($schema);

                $entity = $model::create();

                $entity['child'] = [
                    'id'      => 1,
                    'title'   => 'child record'
                ];
                $child = $entity['child'];

                expect(get_class($child))->toBe(Document::class);
                expect($child->schema())->not->toBe($MyChildModel::definition());
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe('child');

            });

            it("casts object columns", function() {

                $model = $this->model;

                $schema = $model::definition();
                $schema->column('child', [
                    'type'  => 'object'
                ]);

                $entity = $model::create(['child' => []]);

                $child = $entity->get('child');
                expect(get_class($child))->toBe(Document::class);
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe('child');

            });

            it("casts undefined object columns", function() {

                $model = $this->model;
                $entity =$model::create(['child' => []]);

                $child = $entity->get('child');
                expect(get_class($child))->toBe(Document::class);
                expect($child->parents()->get($entity))->toBe('child');
                expect($child->basePath())->toBe('child');

            });

            it("casts undefined arrays of objects columns", function() {

                $model = $this->model;
                $entity = $model::create(['childs' => [
                    ['child1' => []],
                    ['child2' => []],
                ]]);

                $childs = $entity->get('childs');
                expect(get_class($childs))->toBe(Collection::class);
                expect($childs->parents()->get($entity))->toBe('childs');
                expect($childs->basePath())->toBe('childs');

                $child1 = $childs[0];
                expect(get_class($child1))->toBe(Document::class);
                expect($child1->parents()->get($childs))->toBe('*');
                expect($child1->basePath())->toBe('childs');

                $child2 = $childs[1];
                expect(get_class($child2))->toBe(Document::class);
                expect($child2->parents()->get($childs))->toBe('*');
                expect($child2->basePath())->toBe('childs');

            });

        });

    });

    describe("->modified()", function() {

        afterEach(function() {
          Image::reset();
        });

        it("checks nested arbitraty value modifications in cascade", function() {

            Image::definition()->lock(false);

            $image = Image::create();
            $image->set('a.nested.value', 'hello');
            expect($image->modified())->toBe(true);
            expect($image->a->modified())->toBe(false);
            expect($image->a->nested->modified())->toBe(false);

            $image->amend();
            expect($image->modified())->toBe(false);
            expect($image->a->modified())->toBe(false);
            expect($image->a->nested->modified())->toBe(false);

            $image->set('a.nested.value', 'world');
            expect($image->modified())->toBe(true);
            expect($image->a->modified())->toBe(true);
            expect($image->a->nested->modified())->toBe(true);

            $image->amend();
            expect($image->modified())->toBe(false);
            expect($image->a->modified())->toBe(false);
            expect($image->a->nested->modified())->toBe(false);

        });

        it("checks belongsTo relations modifications", function() {

            $image = Image::create();
            $image->set('gallery', ['id' => '1', 'name' => 'MyGallery']);
            $image->amend();
            expect($image->modified())->toBe(false);
            expect($image->get('gallery')->modified())->toBe(false);

            $image->set('gallery', ['id' => '2', 'name' => 'My New Gallery']);
            expect($image->modified())->toBe(true);

            $image->set('gallery', null);
            expect($image->modified())->toBe(true);

        });

        it("checks hasMany relations modifications", function() {

            $image = Image::create();
            $image->set('images_tags.0', ['id' => '1', 'image_id' => '1', 'tag_id' => '1']);
            $image->amend();
            expect($image->modified())->toBe(false);

            $image->set('images_tags.1', ['id' => '2', 'image_id' => '2', 'tag_id' => '2']);
            expect($image->modified())->toBe(false);
            expect($image->modified(['embed' => 'images_tags']))->toBe(true);
            expect($image->modified(['embed' => 'tags']))->toBe(true);

            $image->set('images_tags', null);
            expect($image->modified())->toBe(false);
            expect($image->modified(['embed' => 'images_tags']))->toBe(true);
            expect($image->modified(['embed' => 'tags']))->toBe(true);

        });

        it("sets a single hasManyThrough relation", function() {

            $image = Image::create();
            $image->set('tags.0', ['id' => '1', 'name' => 'landscape']);
            $image->amend();
            expect($image->modified())->toBe(false);

            $image->set('tags.1', ['id' => '2', 'name' => 'galaxy']);
            expect($image->modified())->toBe(false);
            expect($image->modified(['embed' => 'tags']))->toBe(true);

        });

    });

    describe("->validates()", function() {

        beforeEach(function() {
            $validator = Gallery::validator();
            $validator->rule('name', 'not:empty');

            $validator = Image::validator();
            $validator->rule('name', 'not:empty');

            $validator = Tag::validator();
            $validator->rule('name', 'not:empty');
        });

        afterEach(function() {
            Gallery::reset();
            Image::reset();
            Tag::reset();
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
            expect($image->validates(['embed' => true]))->toBe(false);
            expect($image->errors())->toBe([
                'name' => ['is required'],
                'gallery' => [
                    'name' => ['is required']
                ]
            ]);

            $image->name = 'new image';
            expect($image->validates(['embed' => true]))->toBe(false);
            expect($image->errors())->toBe([
                'gallery' => [
                    'name' => ['is required']
                ]
            ]);

            $image->gallery->name = 'new gallery';
            expect($image->validates(['embed' => true]))->toBe(true);
            expect($image->errors())->toBe([]);

        });

        it("validates a hasMany nested entities", function() {

            $gallery = Gallery::create();
            $gallery->images[] = Image::create();
            $gallery->images[] = Image::create();

            expect($gallery->validates(['embed' => true]))->toBe(false);
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
            expect($gallery->validates(['embed' => true]))->toBe(false);
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
            expect($gallery->validates(['embed' => true]))->toBe(false);
            expect($gallery->errors())->toBe([
                'images' => [
                    [],
                    ['name' => ['must not be a empty']]
                ]
            ]);

            $gallery->name = 'new gallery';
            $gallery->images[0]->name = 'image1';
            $gallery->images[1]->name = 'image2';
            expect($gallery->validates(['embed' => true]))->toBe(true);
            expect($gallery->errors())->toBe([]);

        });

        it("validates a hasManyThrough nested entities", function() {

            $image = Image::create();
            $image->tags[] = Tag::create();
            $image->tags[] = Tag::create();

            expect($image->validates(['embed' => true]))->toBe(false);
            expect($image->errors())->toBe([
                'name'   => ['is required'],
                'images_tags' => [
                   ['tag' => ['name' => ['is required']]],
                   ['tag' => ['name' => ['is required']]]
                ],
                'tags' => [
                   ['name' => ['is required']],
                   ['name' => ['is required']]
                ]
            ]);

            $image->name = '';
            $image->tags[0]->name = '';
            $image->tags[1]->name = '';
            expect($image->validates(['embed' => true]))->toBe(false);
            expect($image->errors())->toBe([
                'name'   => ['must not be a empty'],
                'images_tags' => [
                   ['tag' => ['name' => ['must not be a empty']]],
                   ['tag' => ['name' => ['must not be a empty']]]
                ],
                'tags' => [
                   ['name' => ['must not be a empty']],
                   ['name' => ['must not be a empty']]
                ]
            ]);

            $image->name = 'new gallery';
            $image->tags[0]->name = 'image1';
            $image->tags[1]->name = '';
            expect($image->validates(['embed' => true]))->toBe(false);
            expect($image->errors())->toBe([
                'images_tags' => [
                   [],
                   ['tag' => ['name' => ['must not be a empty']]]
                ],
                'tags' => [
                    [],
                    ['name' => ['must not be a empty']]
                ]
            ]);

            $image->name = 'new gallery';
            $image->tags[0]->name = 'image1';
            $image->tags[1]->name = 'image2';
            expect($image->validates(['embed' => true]))->toBe(true);
            expect($image->errors())->toBe([]);

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

        it("invalidates a belongsTo nested entity", function() {

            $image = Image::create();
            $image->gallery = Gallery::create();

            $image->invalidate([
                'name' => ['is required'],
                'gallery' => [
                    'name' => ['is required']
                ]
            ]);

            expect($image->gallery->errors())->toBe([
                'name' => ['is required']
            ]);

        });

        it("doesn't invalidate embedded document", function() {

            $model = Double::classname(['extends' => Model::class]);

            $schema = $model::definition();
            $schema->column('id', ['type' => 'serial']);
            $schema->column('timeSheet', ['type' => 'object', 'default' => []]);

            $entity = $model::create();

            $entity->invalidate([
                'timeSheet' => ['is invalid']
            ]);

            expect($entity->errors())->toBe([
                'timeSheet' => ['is invalid']
            ]);

        });

    });

    describe("->save()", function() {

        afterEach(function() {

            Image::reset();
            Gallery::reset();

        });

        it("validates by default", function() {

            $image = Image::create([]);
            Image::validator()->rule('name', 'not:empty');

            expect($image->save())->toBe(false);
            expect($image->exists())->toBe(false);

        });

        it("doesn't validates direct relationships by default", function() {

            Gallery::validator()->rule('name', 'not:empty');

            $image = Image::create([
                'name'    => 'amiga_1200.jpg',
                'title'   => 'Amiga 1200',
                'gallery' => []
            ]);

            $schema = $image->schema();

            allow($schema)->toReceive('bulkInsert')->andReturn(true);
            allow($schema)->toReceive('bulkUpdate')->andReturn(true);

            expect($schema)->toReceive('save')->with($image, [
                'validate' => true,
                'embed' => false
            ]);

            expect($image->save())->toBe(true);
        });

        it("validates embedded relationships", function() {

            Gallery::validator()->rule('name', 'not:empty');

            $image = Image::create([
                'name'    => 'amiga_1200.jpg',
                'title'   => 'Amiga 1200',
                'gallery' => []
            ]);

            $schema = $image->schema();
            allow($schema)->toReceive('bulkInsert')->andReturn(true);
            allow($schema)->toReceive('bulkUpdate')->andReturn(true);

            allow($image->gallery)->toReceive('__isset')->andReturn(true);

            expect($image->save(['embed' => 'gallery']))->toBe(false);

        });

    });

    describe("->hierarchy()", function() {

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

            // Because image.images_tags and tag.images_tags collections are differents
            expect($image->hierarchy())->toBe([
                'images_tags.tag.images_tags',
                'tags'
            ]);

        });

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
                'images.images_tags.tag',
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
                'gallery_id' => null,
                'images_tags' => [
                    [
                        'tag_id' => null,
                        'tag' => [
                            'name' => 'tag1',
                            'images_tags' => [
                                [
                                    "image_id" => null
                                ]
                            ]
                        ]
                    ]
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

            expect($image->to('array'))->toEqual([
                'title' => 'Amiga 1200',
                'gallery' => ['name' => 'Gallery 1', 'tag_ids' => []],
                'gallery_id' => null,
                'images_tags' => [
                    ['tag_id' => null, 'tag' => ['name' => 'Computer']],
                    ['tag_id' => null, 'tag' => ['name' => 'Science']]
                ],
                'tags'  => [
                    ['name' => 'Computer'],
                    ['name' => 'Science']
                ]
            ]);

            expect($image->to('array', ['embed' => ['gallery']]))->toEqual([
                'title'   => 'Amiga 1200',
                'gallery_id' => null,
                'gallery' => ['name' => 'Gallery 1', 'tag_ids' => []],
            ]);

            expect($image->to('array', ['embed' => false]))->toEqual([
                'title' => 'Amiga 1200',
                'gallery_id' => null
            ]);
        });

    });

    describe("->__toString()", function() {

        it("returns the key as string", function() {

            $model = $this->model;
            $entity = $model::create(['id' => 1]);
            expect((string) $entity)->toBe('1');

        });

    });

});