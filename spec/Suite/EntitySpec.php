<?php
namespace Chaos\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\ChaosException;
use Chaos\Model;
use Chaos\Schema;

use Kahlan\Plugin\Stub;

use Chaos\Spec\Fixture\Model\Gallery;
use Chaos\Spec\Fixture\Model\Image;
use Chaos\Spec\Fixture\Model\Tag;

describe("Entity", function() {

    before(function() {
        $this->model = Stub::classname(['extends' => Model::class]);
    });

    beforeEach(function() {
        $model = $this->model;
        $schema = $model::schema();
        $schema->set('id', ['type' => 'serial']);
    });

    afterEach(function() {
        $model = $this->model;
        $model::reset();
    });

    describe("->__construct()", function() {

        it("loads the data", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $model = $this->model;
            $entity = new $model(['data' => [
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]]);
            expect($entity->title)->toBe('Hello');
            expect($entity->body)->toBe('World');
            expect($entity->created)->toBe($date);
            expect($entity)->toHaveLength(3);

        });

        it("throws an exception if exists is `null` but no record actually exists", function() {

            $model = $this->model;
            Stub::on($model)->method('::id', function() { return; });

            $closure = function() {
                $model = $this->model;
                $entity = $model::create([
                    'id' => 1,
                    'title'   => 'Good Bye',
                    'body'    => 'Folks'
                ], ['exists' => null]);
            };

            expect($closure)->toThrow(new ChaosException("The entity id:`1` doesn't exists."));

        });

    });

    describe("->exists()", function() {

        it("returns the exists value", function() {

            $model = $this->model;
            $entity = $model::create([], ['exists' => true]);
            expect($entity->exists())->toBe(true);

        });

    });

    describe("->parent()", function() {

        it("sets a parent", function() {

            $parent = Stub::create();
            $model = $this->model;
            $entity = $model::create();
            $entity->parent($parent);
            expect($entity->parent())->toBe($parent);

        });

        it("returns the parent", function() {

            $parent = Stub::create();
            $model = $this->model;
            $entity = $model::create([], ['parent' => $parent]);
            expect($entity->parent())->toBe($parent);

        });

    });

    describe("->rootPath()", function() {

        it("returns the root path", function() {

            $model = $this->model;
            $entity = $model::create([], ['rootPath' => 'items']);
            expect($entity->rootPath())->toBe('items');

        });

    });

    describe("->primaryKey()", function() {

        it("returns the entity's primary key value", function() {

            $model = $this->model;
            $entity = $model::create([
                'id'      => 123,
                'title'   => 'Hello',
                'body'    => 'World'
            ]);
            expect($entity->primaryKey())->toBe(123);

        });

        it("throws an exception if the schema has no primary key defined", function() {
            $schema = new Schema(['primaryKey' => null]);

            $model = $this->model;
            $model::config(compact('schema'));

            $closure = function() {
                $model = $this->model;
                $entity = $model::create([
                    'id'      => 123,
                    'title'   => 'Hello',
                    'body'    => 'World'
                ]);
                $entity->primaryKey();
            };
            expect($closure)->toThrow(new ChaosException("No primary key has been defined for `{$model}`'s schema."));

        });

    });

    describe("->sync()", function() {

        it("syncs an entity to its persisted value", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity->modified = 'modified';

            expect($entity->exists())->toBe(false);
            expect($entity->primaryKey())->toBe(null);
            expect($entity->modified('modified'))->toBe(true);

            $entity->sync(123, ['added' => 'added'], ['exists' => true]);

            expect($entity->exists())->toBe(true);
            expect($entity->primaryKey())->toBe(123);
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
                expect($entity->primaryKey())->toBe(null);
                expect($entity->modified('modified'))->toBe(true);

                $entity->sync(null, ['added' => 'added'], ['exists' => true]);

                expect($entity->exists())->toBe(true);
                expect($entity->primaryKey())->toBe(null);
                expect($entity->modified('modified'))->toBe(false);
                expect($entity->modified('added'))->toBe(false);
                expect($entity->added)->toBe('added');

            });

        });

    });

    describe("->set()", function() {

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

    });

    describe("->__set()", function() {

        it("sets value", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity->hello = 'world';
            expect($entity->hello)->toBe('world');

        });

        it("sets a value using a dedicated method", function() {

            $entity = Stub::create([
                'extends' => $this->model,
                'methods' => ['setHelloBoy']
            ]);
            Stub::on($entity)->method('setHelloBoy', function($data) {
                return 'Hi ' . $data;
            });

            $entity->hello_boy = 'boy';
            expect($entity->hello_boy)->toBe('Hi boy');

        });

    });

    describe("->get()", function() {

        it("returns `null` for undefined fields", function() {

            $model = $this->model;
            $entity = $model::create();
            expect($entity->foo)->toBe(null);

        });

        it("returns all raw datas with no parameter", function() {

            $date = time();
            $model = $this->model;
            $entity = $model::create([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]);
            expect($entity->get())->toBe([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]);

        });

        it("gets a value using a dedicated method", function() {

            $entity = Stub::create([
                'extends' => $this->model,
                'methods' => ['getHelloBoy']
            ]);

            Stub::on($entity)->method('getHelloBoy', function($data) {
                return 'Hi ' . $data;
            });

            $entity->hello_boy = 'boy';
            expect($entity->hello_boy)->toBe('Hi boy');

        });

        it("lazy loads relations", function() {

            $model = $this->model;
            $schema = $model::schema();

            $schema->bind('abc', [
                'relation' => 'hasOne',
                'to'       => 'TargetModel',
            ]);

            $relation = Stub::create();
            $entity = $model::create();

            Stub::on($entity)->method('::relation', function() use ($relation) {
                return $relation;
            });

            expect($relation)->toReceive('get')->with($entity);

            $entity->abc;

        });

    });

    describe("->__get()", function() {

        it("gets value", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity->hello = 'world';
            expect($entity->hello)->toBe('world');
        });

        it("throws an exception if the field name is not valid", function() {

           $closure = function() {
                $model = $this->model;
                $entity = $model::create();
                $empty = '';
                $entity->{$empty};
            };
            expect($closure)->toThrow(new ChaosException("Field name can't be empty."));

        });

    });

    describe("->persisted()", function() {

        it("returns persisted data", function() {

            $model = $this->model;

            $entity = $model::create([
                'id'      => 1,
                'title'   => 'Hello',
                'body'    => 'World'
            ], ['exists' => true]);

            $entity->set([
                'id' => 1,
                'title'   => 'Good Bye',
                'body'    => 'Folks'
            ]);

            expect($entity->persisted('title'))->toBe('Hello');
            expect($entity->persisted('body'))->toBe('World');

            expect($entity->title)->toBe('Good Bye');
            expect($entity->body)->toBe('Folks');

            expect($entity->modified('title'))->toBe(true);
            expect($entity->modified('body'))->toBe(true);

        });

        it("returns all persisted data with no parameter", function() {

            $model = $this->model;

            $entity = $model::create([
                'id'      => 1,
                'title'   => 'Hello',
                'body'    => 'World'
            ], ['exists' => true]);

            $entity->set([
                'id' => 1,
                'title'   => 'Good Bye',
                'body'    => 'Folks'
            ]);

            expect($entity->persisted())->toBe([
                'id'      => 1,
                'title'   => 'Hello',
                'body'    => 'World'
            ]);

        });

    });

    describe("->modified()", function() {

        it("returns a boolean indicating if a field has been modified", function() {

            $model = $this->model;
            $entity = $model::create(['title' => 'original'], ['exists' => true]);

            expect($entity->modified('title'))->toBe(false);

            $entity->title = 'modified';
            expect($entity->modified('title'))->toBe(true);

        });

        it("returns `false` if a field has been updated with a same scalar value", function() {

            $model = $this->model;
            $entity = $model::create(['title' => 'original'], ['exists' => true]);

            expect($entity->modified('title'))->toBe(false);

            $entity->title = 'original';
            expect($entity->modified('title'))->toBe(false);

        });

        it("returns `false` if a field has been updated with a similar object value", function() {

            $model = $this->model;
            $entity = $model::create(['body'  => (object) 'body'], ['exists' => true]);

            expect($entity->modified('body'))->toBe(false);

            $entity->title = (object) 'body';
            expect($entity->modified('body'))->toBe(false);

        });

        it("delegates the job for values which has a `modified()` method", function() {

            $model = $this->model;
            $child = Stub::classname(['extends' => $this->model]);
            $model::schema()->set('child', [
                'type' => 'object',
                'to'   => $child
            ]);

            $subentity = $child::create(['field' => 'value'], ['exists' => true]);

            $entity = $model::create(['child' => $subentity], ['exists' => true]);

            expect($entity->modified())->toBe(false);

            $entity->child->field = 'modified';
            expect($entity->modified())->toBe(true);

        });

        it("returns `true` when an unexisting field has been added", function() {

            $model = $this->model;
            $entity = $model::create([], ['exists' => true]);

            $entity->modified = 'modified';

            expect($entity->modified())->toBe(true);

        });

        it("returns `true` when a field is removed", function() {

            $model = $this->model;
            $entity = $model::create(['title' => 'original'], ['exists' => true]);

            expect($entity->modified('title'))->toBe(false);

            unset($entity->title);
            expect($entity->modified('title'))->toBe(true);

        });

        it("returns `false` when an unexisting field is checked", function() {

            $model = $this->model;
            $entity = $model::create([], ['exists' => true]);
            expect($entity->modified('unexisting'))->toBe(false);

        });

    });

    describe("->offsetExists()", function() {

        it("returns true if a element exist", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity['field1'] = 'foo';
            $entity['field2'] = null;

            expect(isset($entity['field1']))->toBe(true);
            expect(isset($entity['field2']))->toBe(true);

        });

        it("returns false if a element doesn't exist", function() {

            $model = $this->model;
            $entity = $model::create();
            expect(isset($entity['undefined']))->toBe(false);

        });

    });

    describe("->offsetSet/offsetGet()", function() {

        it("allows array access", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity['field1'] = 'foo';
            expect($entity['field1'])->toBe('foo');
            expect($entity)->toHaveLength(1);

        });

        it("sets at a specific key", function() {

            $model = $this->model;
            $entity = $model::create();
            $entity['mykey'] = 'foo';
            expect($entity['mykey'])->toBe('foo');
            expect($entity)->toHaveLength(1);

        });

        it("throws an exception for invalid key", function() {
            $closure = function() {
                $model = $this->model;
                $entity = $model::create();
                $entity[] = 'foo';
            };
            expect($closure)->toThrow(new ChaosException("Field name can't be empty."));

        });

        context("when a model is defined", function() {

            beforeEach(function() {
                $this->model = Stub::classname(['extends' => $this->model]);
            });

            it("autoboxes setted data", function() {

                $model = $this->model;
                $childModel = Stub::classname(['extends' => $this->model]);

                $schema = new Schema(['model' => $model]);
                $schema->set('child', [
                    'type' => 'object',
                    'model' => $childModel
                ]);

                $model::config(compact('schema'));

                $entity = $model::create();

                $entity['child'] = [
                    'id'      => 1,
                    'title'   => 'child record',
                    'enabled' => true
                ];
                $child = $entity['child'];

                expect($child)->toBeAnInstanceOf($childModel);
                expect($child->parent())->toBe($entity);
                expect($child->rootPath())->toBe('child');

            });

        });

    });

    describe("->offsetUnset()", function() {

        it("unsets items", function() {

            $data = [
                'id'      => 1,
                'title'   => 'test record',
                'body'    => 'test body',
                'enabled' => true
            ];

            $model = $this->model;
            $entity = $model::create($data);
            unset($entity['body']);
            unset($entity['enabled']);

            expect($entity)->toHaveLength(2);
            expect($entity->data())->toBe([
                'id'      => 1,
                'title'   => 'test record'
            ]);

        });

        it("unsets all items in a foreach", function() {

            $data = [
                'field1' => 'Delete me',
                'field2' => 'Delete me'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            foreach ($entity as $i => $word) {
                unset($entity[$i]);
            }

            expect($entity->data())->toBe([]);

        });

        it("unsets last items in a foreach", function() {

            $data = [
                'field1' => 'Hello',
                'field2' => 'Hello again!',
                'field3' => 'Delete me'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            foreach ($entity as $i => $word) {
                if ($word === 'Delete me') {
                    unset($entity[$i]);
                }
            }

            expect($entity->data())->toBe([
                'field1' => 'Hello',
                'field2' => 'Hello again!'
            ]);

        });

        it("unsets first items in a foreach", function() {

            $data = [
                'field1' => 'Delete me',
                'field2' => 'Hello',
                'field3' => 'Hello again!'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            foreach ($entity as $i => $word) {
                if ($word === 'Delete me') {
                    unset($entity[$i]);
                }
            }

            expect($entity->data())->toBe([
                'field2' => 'Hello',
                'field3' => 'Hello again!'
            ]);

        });

        it("doesn't skip element in foreach", function() {

            $data = [
                'field1' => 'Delete me',
                'field2' => 'Hello',
                'field3' => 'Delete me',
                'field4' => 'Hello again!'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            $loop = 0;
            foreach ($entity as $i => $word) {
                if ($word === 'Delete me') {
                    unset($entity[$i]);
                }
                $loop++;
            }

            expect($loop)->toBe(4);

            expect($entity->data())->toBe([
                'field2' => 'Hello',
                'field4' => 'Hello again!'
            ]);

        });

    });

    describe("->key()", function() {

        it("returns current key", function() {

            $data = ['field' => 'value'];
            $model = $this->model;
            $entity = $model::create($data);
            $value = $entity->key();
            expect($value)->toBe('field');

        });

        it("returns null if non valid", function() {

            $model = $this->model;
            $entity = $model::create();
            $value = $entity->key();
            expect($value)->toBe(null);

        });

    });

    describe("->current()", function() {

        it("returns the current value", function() {

            $data = ['field' => 'value'];
            $model = $this->model;
            $entity = $model::create($data);
            $value = $entity->current();
            expect($value)->toBe('value');

        });

    });

    describe("->next()", function() {

        it("returns the next value", function() {

            $data = [
                'field1' => 'value1',
                'field2' => 'value2'
            ];

            $model = $this->model;
            $entity = $model::create($data);
            $value = $entity->next();
            expect($value)->toBe('value2');

        });

    });

    describe("->prev()", function() {

        it("navigates through collection", function() {

            $data = [
                'id'      => 1,
                'title'   => 'test record',
                'body'    => 'test body'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            $entity->rewind();
            expect($entity->next())->toBe('test record');
            expect($entity->next())->toBe('test body');
            expect($entity->next())->toBe(null);

            $entity->end();
            expect($entity->prev())->toBe('test record');
            expect($entity->prev())->toBe(1);
            expect($entity->prev())->toBe(null);

        });

    });

    describe("->rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            $data = [
                'id'      => 1,
                'title'   => 'test record',
                'body'    => 'test body'
            ];

            $model = $this->model;
            $entity = $model::create($data);

            expect($entity->end())->toBe('test body');
            expect($entity->rewind())->toBe(1);
            expect($entity->end())->toBe('test body');
            expect($entity->rewind())->toBe(1);

        });

    });

    describe("->valid()", function() {

        it("returns true only when the collection is valid", function() {

            $model = $this->model;
            $entity = $model::create();
            expect($entity->valid())->toBe(false);

            $data = [
                'id'      => 1,
                'title'   => 'test record',
                'body'    => 'test body'
            ];
            $entity = $model::create($data);
            expect($entity->valid())->toBe(true);

        });

    });

    describe("->count()", function() {

        it("returns 0 on empty", function() {

            $model = $this->model;
            $entity = $model::create();
            expect($entity)->toHaveLength(0);

        });

        it("returns the number of items in the collection", function() {

            $data = [
                'id'      => 1,
                'title'   => 'test record',
                'body'    => 'test body',
                'enabled' => true,
                'null'    => null,
                'onject'  => new stdClass()
            ];

            $model = $this->model;
            $entity = $model::create($data);
            expect($entity)->toHaveLength(6);

        });

    });

    describe("->to()", function() {

        it("exports into an array", function() {

            $data = [
                'id'      => 1,
                'title'   => 'test record'
            ];

            $model = $this->model;
            $entity = $model::create($data);
            expect($entity->to('array'))->toBe($data);

        });

        it("supports the `'embed'` option", function() {

            $image = Image::create([
                'title' => 'Amiga 1200'
            ]);
            $image->tags[] = ['name' => 'Computer'];
            $image->tags[] = ['name' => 'Science'];

            $image->gallery = ['name' => 'Gallery 1'];

            expect($image->to('array', ['embed' => true]))->toBe([
                'title' => 'Amiga 1200',
                'tags' => [
                    ['name' => 'Computer'],
                    ['name' => 'Science']
                ],
                'images_tags' => [
                    ['tag' => ['name' => 'Computer']],
                    ['tag' => ['name' => 'Science']]
                ],
                'gallery' => ['name' => 'Gallery 1']
            ]);

            expect($image->to('array', ['embed' => ['gallery']]))->toBe([
                'title' => 'Amiga 1200',
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
                'id'      => 1,
                'title'   => 'test record'
            ];

            $model = $this->model;
            $entity = $model::create($data);
            expect((string) $entity)->toBe('test record');

        });

    });

    describe("->validate()", function() {

        beforeEach(function() {
            $validator = Gallery::validator();
            $validator->rule('name', 'not:empty');

            $validator = Image::validator();
            $validator->rule('name', 'not:empty');
        });

        afterEach(function() {
            Gallery::config();
            Image::config();
        });

        it("validate an entity", function() {

            $gallery = Gallery::create();
            expect($gallery->validate())->toBe(false);
            expect($gallery->errors())->toBe(['name' => ['is required']]);

            $gallery->name = '';
            expect($gallery->validate())->toBe(false);
            expect($gallery->errors())->toBe(['name' => ['must not be a empty']]);

            $gallery->name = 'new gallery';
            expect($gallery->validate())->toBe(true);
            expect($gallery->errors())->toBe([]);

        });

        it("validate an nested entities", function() {

            $gallery = Gallery::create();
            $gallery->images[] = Image::create();
            $gallery->images[] = Image::create();

            expect($gallery->validate())->toBe(false);
            expect($gallery->errors())->toBe([
                'name' => ['is required'],
                'images' => [
                    ['name' => ['is required']],
                    ['name' => ['is required']]
                ]
            ]);

            $gallery->name = '';
            $gallery->images[0]->name = '';
            $gallery->images[1]->name = '';
            expect($gallery->validate())->toBe(false);
            expect($gallery->errors())->toBe([
                'name' => ['must not be a empty'],
                'images' => [
                    ['name' => ['must not be a empty']],
                    ['name' => ['must not be a empty']]
                ]
            ]);

            $gallery->name = 'new gallery';
            $gallery->images[0]->name = 'image1';
            $gallery->images[1]->name = 'image2';
            expect($gallery->validate())->toBe(true);
            expect($gallery->errors())->toBe([
                'images' => [
                    [],
                    []
                ]
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

        it("validates direct relationships by default", function() {

            Gallery::validator()->rule('name', 'not:empty');

            $image = Image::create([
                'name' => 'amiga_1200.jpg',
                'title' => 'Amiga 1200',
                'gallery' => []
            ]);
            expect($image->save())->toBe(false);
            expect($image->exists())->toBe(false);

        });

    });

    describe("->to('array')", function() {

        it("exports data using `'array'` formatter handlers", function() {

            $model = $this->model;

            $schema = $model::schema();
            $schema->set('created', ['type' => 'date']);

            $entity = new $model(['data' => [
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => new DateTime('2014-10-26 00:25:15')
            ]]);

            expect($entity->data(['format' => 'Y-m-d']))->toBe([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => '2014-10-26'
            ]);

        });

        it("supports recursive structures", function() {

            $data = [
                'name' => 'amiga_1200.jpg',
                'title' => 'Amiga 1200',
                'tags' => [
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

    });

});