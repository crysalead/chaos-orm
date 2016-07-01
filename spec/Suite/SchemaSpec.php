<?php
namespace Chaos\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\Schema;
use Chaos\Model;

use Kahlan\Plugin\Stub;

use Chaos\Spec\Fixture\Model\Gallery;
use Chaos\Spec\Fixture\Model\Image;
use Chaos\Spec\Fixture\Model\ImageTag;
use Chaos\Spec\Fixture\Model\Tag;

describe("Schema", function() {

    beforeEach(function() {
        $this->schema = Image::definition();
    });

    afterEach(function() {
        Image::reset();
    });

    describe("->__construct()", function() {

        it("correctly sets config options", function() {

            $connection = Stub::create();
            $conventions = Stub::create();

            Stub::on($connection)->method('formatters')->andReturn([]);

            $schema = new Schema([
                'connection'  => $connection,
                'source'      => 'image',
                'model'       => Image::class,
                'key'         => 'key',
                'locked'      => false,
                'columns'     => ['id' => 'serial', 'age' => 'integer'],
                'meta'        => ['some' => ['meta']],
                'conventions' => $conventions
            ]);

            expect($schema->connection())->toBe($connection);
            expect($schema->source())->toBe('image');
            expect($schema->model())->toBe(Image::class);
            expect($schema->key())->toBe('key');
            expect($schema->locked())->toBe(false);
            expect($schema->fields())->toBe(['id', 'age']);
            expect($schema->meta())->toBe(['some' => ['meta']]);
            expect($schema->conventions())->toBe($conventions);

        });

    });

    describe("->connection()", function() {

        it("gets/sets the connection", function() {

            $connection = Stub::create();
            $schema = new Schema();

            expect($schema->connection($connection))->toBe($schema);
            expect($schema->connection())->toBe($connection);

        });

    });

    describe("->source()", function() {

        it("gets/sets the source", function() {

            $schema = new Schema();

            expect($schema->source('source_name'))->toBe($schema);
            expect($schema->source())->toBe('source_name');

        });

    });

    describe("->model()", function() {

        it("gets/sets the conventions", function() {

            $schema = new Schema();

            expect($schema->model(Image::class))->toBe($schema);
            expect($schema->model())->toBe(Image::class);

        });

    });

    describe("->locked()", function() {

        it("gets/sets the lock value", function() {

            $schema = new Schema();

            expect($schema->locked(false))->toBe($schema);
            expect($schema->locked())->toBe(false);

        });

    });

    describe("->meta()", function() {

        it("gets/sets the meta value", function() {

            $schema = new Schema();

            expect($schema->meta(['some' => ['meta']]))->toBe($schema);
            expect($schema->meta())->toBe(['some' => ['meta']]);

        });

    });

    describe("->key()", function() {

        it("gets/sets the primary key value", function() {

            $schema = new Schema();

            expect($schema->key('_id'))->toBe($schema);
            expect($schema->key())->toBe('_id');

        });

    });

    describe("->names()", function() {

        it("gets the schema field names", function() {

            $names = $this->schema->names();
            sort($names);
            expect($names)->toBe(['gallery_id', 'id', 'name', 'score', 'title']);

        });

    });

    describe("->fields()", function() {

        it("returns all fields", function() {

            expect($this->schema->fields())->toBe(['id', 'gallery_id', 'name', 'title', 'score']);

        });

        it("filters out virtual fields", function() {

            $this->schema->column('virtualField', ['virtual' => true]);
            $fields = $this->schema->fields();
            expect(isset($fields['virtualField']))->toBe(false);

        });

    });

    describe("->columns()", function() {

        it("returns all columns", function() {

            expect($this->schema->columns())->toBe([
                'id' => [
                    'type'  => 'serial',
                    'array' => false,
                    'null'  => false
                ],
                'gallery_id' => [
                    'type'  => 'integer',
                    'array' => false,
                    'null'  => true
                ],
                'name' => [
                    'type'    => 'string',
                    'array'   => false,
                    'null'    => true
                ],
                'title' => [
                    'type'    => 'string',
                    'length'  => 50,
                    'array'   => false,
                    'null'    => true
                ],
                'score' => [
                    'type'    => 'float',
                    'array'   => false,
                    'null'    => true
                ]
            ]);

        });

    });

    it("returns defaults", function() {

        $this->schema->column('name', ['type' => 'string', 'default' => 'Enter The Name Here']);
        $this->schema->column('title', ['type' => 'string', 'default' => 'Enter The Title Here', 'length' => 50]);

        expect($this->schema->defaults())->toBe([
            'name'       => 'Enter The Name Here',
            'title'      => 'Enter The Title Here'
        ]);

    });

    describe("->type()", function() {

        it("returns a field type", function() {

            expect($this->schema->type('id'))->toBe('serial');

        });

    });

    describe("->column()", function() {

        beforeEach(function() {
            $this->schema = new Schema();
        });

        it("gets the field", function() {

            $schema = Image::definition();

            expect($schema->column('id'))->toBe([
                'type'  => 'serial',
                'array' => false,
                'null'  => false
            ]);

            expect($schema->column('gallery_id'))->toBe([
                'type'  => 'integer',
                'array' => false,
                'null'  => true
            ]);

            expect($schema->column('name'))->toBe([
                'type'  => 'string',
                'array' => false,
                'null'  => true
            ]);

            expect($schema->column('title'))->toBe([
                'type'  => 'string',
                'length'  => 50,
                'array' => false,
                'null'  => true
            ]);

            expect($schema->column('score'))->toBe([
                'type'  => 'float',
                'array' => false,
                'null'  => true
            ]);

        });

        it("sets a field with a specific type", function() {

            $this->schema->column('age', ['type' => 'integer']);
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field with a specific type using the array syntax", function() {

            $this->schema->column('age', ['integer']);
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field with a specific type using the string syntax", function() {

            $this->schema->column('age', 'integer');
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field as an array", function() {

            $this->schema->column('ids', ['type' => 'integer', 'array' => true]);
            expect($this->schema->column('ids'))->toBe([
                'type' => 'integer',
                'array' => true,
                'null' => true
            ]);

        });

        it("sets a field with custom options", function() {

            $this->schema->column('name', ['type' => 'integer', 'length' => 11, 'use' => 'bigint']);
            expect($this->schema->column('name'))->toBe([
                'type'   => 'integer',
                'length' => 11,
                'use'    => 'bigint',
                'array'  => false,
                'null'   => true
            ]);

        });

        it("sets nested fields", function() {

            $schema = new Schema();
            $schema->column('preferences', ['type' => 'object']);
            $schema->column('preferences.blacklist', ['type' => 'object']);
            $schema->column('preferences.blacklist.projects', ['type' => 'id', 'array' => true, 'default' => []]);
            $schema->column('preferences.mail', ['type' => 'object']);
            $schema->column('preferences.mail.enabled', ['type' => 'boolean', 'default' => true]);
            $schema->column('preferences.mail.frequency', ['type' => 'integer', 'default' => 24]);

            $document = $schema->cast(null, []);

            expect($document->data())->toEqual([
                'preferences' => [
                    'blacklist' => [
                        'projects' => []
                    ],
                    'mail' => [
                        'enabled' => true,
                        'frequency' => 24
                    ]
                ]
            ]);

            $document['preferences.mail.enabled'] = 0;
            expect($document['preferences.mail.enabled'])->toBe(false);

        });

        context("with a dynamic getter", function() {

            context("with a normal field", function() {

                beforeEach(function() {

                    $this->schema = new Schema();
                    $this->schema->column('date', ['type' => 'string']);
                    $this->schema->column('time', ['type' => 'string']);
                    $this->schema->column('datetime', [
                        'type' => 'datetime',
                        'getter' => function($entity, $data, $name) {
                            return $entity['date'] . ' ' . $entity['time'];
                        }
                    ]);

                });

                it("builds the field", function() {

                    $document = $this->schema->cast(null, [
                        'date' => '2015-05-20',
                        'time' => '21:50:00'
                    ]);
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 21:50:00');
                    expect(isset($document->datetime))->toBe(true);

                });

                it("rebuilds the field on changes", function() {

                    $document = $this->schema->cast(null, [
                        'date' => '2015-05-20',
                        'time' => '21:50:00'
                    ]);
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 21:50:00');

                    $document['time'] = '22:15:00';
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 22:15:00');
                    expect(isset($document->datetime))->toBe(true);

                });

            });

            context("with a virtual field", function() {

                beforeEach(function() {

                    $this->schema = new Schema();
                    $this->schema->column('date', ['type' => 'string']);
                    $this->schema->column('time', ['type' => 'string']);
                    $this->schema->column('datetime', [
                        'type'    => 'datetime',
                        'virtual' => true,
                        'getter'  => function($entity, $data, $name) {
                            return $entity['date'] . ' ' . $entity['time'];
                        }
                    ]);

                });

                it("builds the field", function() {

                    $document = $this->schema->cast(null, [
                        'date' => '2015-05-20',
                        'time' => '21:50:00'
                    ]);
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 21:50:00');
                    expect(isset($document->datetime))->toBe(false);

                });

                it("rebuilds the field on changes", function() {

                    $document = $this->schema->cast(null, [
                        'date' => '2015-05-20',
                        'time' => '21:50:00'
                    ]);
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 21:50:00');

                    $document['time'] = '22:15:00';
                    expect($document->datetime->format('Y-m-d H:i:s'))->toBe('2015-05-20 22:15:00');
                    expect(isset($document->datetime))->toBe(false);

                });

            });

        });

        context("with a dynamic setter", function() {

            context("with a normal field", function() {

                beforeEach(function() {

                    $this->schema = new Schema();
                    $this->schema->column('date', ['type' => 'string']);
                    $this->schema->column('time', ['type' => 'string']);
                    $this->schema->column('datetime', [
                        'type'   => 'string',
                        'setter' => function($entity, $data, $name) {
                            $parts = explode(' ', $data);
                            $entity['date'] = $parts[0];
                            $entity['time'] = $parts[1];
                            return $data;
                        }
                    ]);

                });

                it("builds the field", function() {

                    $document = $this->schema->cast();
                    $document->datetime = '2015-05-20 21:50:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('21:50:00');
                    expect($document->datetime)->toBe('2015-05-20 21:50:00');

                });

                it("rebuilds the field on changes", function() {

                    $document = $this->schema->cast();
                    $document->datetime = '2015-05-20 21:50:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('21:50:00');
                    expect($document->datetime)->toBe('2015-05-20 21:50:00');

                    $document->datetime = '2015-05-20 22:15:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('22:15:00');
                    expect($document->datetime)->toBe('2015-05-20 22:15:00');

                });

            });

            context("with a virtual field", function() {

                beforeEach(function() {

                    $this->schema = new Schema();
                    $this->schema->column('date', ['type' => 'string']);
                    $this->schema->column('time', ['type' => 'string']);
                    $this->schema->column('datetime', [
                        'type'    => 'string',
                        'virtual' => true,
                        'setter'  => function($entity, $data, $name) {
                            $parts = explode(' ', $data);
                            $entity['date'] = $parts[0];
                            $entity['time'] = $parts[1];
                            return $data;
                        }
                    ]);

                });

                it("builds the field", function() {

                    $document = $this->schema->cast();
                    $document->datetime = '2015-05-20 21:50:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('21:50:00');
                    expect(isset($document->datetime))->toBe(false);

                });

                it("rebuilds the field on changes", function() {

                    $document = $this->schema->cast();
                    $document->datetime = '2015-05-20 21:50:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('21:50:00');
                    expect(isset($document->datetime))->toBe(false);

                    $document->datetime = '2015-05-20 22:15:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('22:15:00');
                    expect(isset($document->datetime))->toBe(false);

                });

            });

        });

    });

    describe("->remove()", function() {

        it("removes a field", function() {

            $this->schema->remove('title');
            expect($this->schema->has('title'))->toBe(false);

        });

    });

    describe("->has()", function() {

        it("checks if a schema contain a field name", function() {

            expect($this->schema->has('title'))->toBe(true);
            $this->schema->remove('title');
            expect($this->schema->has('title'))->toBe(false);

        });

        it("checks if a schema contain a virtual field name", function() {

            $this->schema->column('virtualField', ['virtual' => true]);
            expect($this->schema->has('virtualField'))->toBe(true);

        });

    });

    describe("->append()", function() {

        beforeEach(function() {
            $this->schema = new Schema();
            $this->schema->column('id', ['type' => 'serial']);
        });

        context("using an array", function() {

            it("adds some fields to a schema", function() {

                $this->schema->locked(false);

                $this->schema->append([
                    'name'  => ['type' => 'string'],
                    'title' => ['type' => 'string']
                ]);

                $fields = $this->schema->fields();
                ksort($fields);

                expect($fields)->toBe(['id', 'name', 'title']);

            });

        });

        context("using a schema instance", function() {

            it("adds some fields to a schema", function() {

                $extra = new Schema();
                $extra->column('name', ['type' => 'string']);
                $extra->column('title', ['type' => 'string']);

                $this->schema->append($extra);

                $fields = $this->schema->fields();
                ksort($fields);

                expect($fields)->toBe(['id', 'name', 'title']);

            });

        });

    });

    describe("->virtuals()", function() {

        it("returns all virtual fields", function() {

            $this->schema->column('virtualField1', ['virtual' => true]);
            $this->schema->column('virtualField2', ['virtual' => true]);

            expect($this->schema->virtuals())->toBe(['virtualField1', 'virtualField2']);

        });

    });

    describe("->bind()", function() {

        it("binds a relation", function() {

            expect($this->schema->hasRelation('parent'))->toBe(false);

            $this->schema->bind('parent', [
                'relation' => 'belongsTo',
                'to'       => Image::class,
                'keys'     => ['image_id' => 'id']
            ]);

            expect($this->schema->hasRelation('parent'))->toBe(true);

        });

    });

    describe("->unbind()", function() {

        it("unbinds a relation", function() {

            expect($this->schema->hasRelation('gallery'))->toBe(true);

            $this->schema->unbind('gallery');

            expect($this->schema->hasRelation('gallery'))->toBe(false);

        });

    });

    describe("->relations", function() {

        it("returns all relation names", function() {

            $relations = $this->schema->relations();
            sort($relations);

            expect($relations)->toBe(['gallery', 'images_tags', 'tags']);

        });

        it("includes embedded relations using `true` as first parameter", function() {

            $model = Stub::classname(['extends' => Model::class]);

            $schema = new Schema(['model' => $model]);
            $schema->column('embedded', [
                'type' => 'object',
                'model' => $model
            ]);

            expect($schema->relations())->toBe([]);
            expect($schema->relations(true))->toBe(['embedded']);

        });

    });

    describe("->conventions()", function() {

        it("gets/sets the conventions", function() {

            $conventions = Stub::create();
            $schema = new Schema();

            expect($schema->conventions($conventions))->toBe($schema);
            expect($schema->conventions())->toBe($conventions);

        });

    });

    describe("->expand()", function() {

        it("expands schema paths", function() {

            expect($this->schema->expand(['gallery', 'tags']))->toBe([
                'gallery' => null,
                'tags' => null,
                'images_tags.tag' => null
            ]);

        });

        it("perserves values", function() {

            $actual = $this->schema->expand([
                'gallery' => [
                    'conditions' => [
                        'name' => 'My Gallery'
                    ]
                ],
                'tags' => [
                    'conditions' => [
                        'name' => 'landscape'
                    ]
                ]
            ]);

            expect($actual)->toBe([
                'gallery' => [
                    'conditions' => [
                        'name' => 'My Gallery'
                    ]
                ],
                'tags' => [
                    'conditions' => [
                        'name' => 'landscape'
                    ]
                ],
                'images_tags.tag' => [
                    'conditions' => [
                        'name' => 'landscape'
                    ]
                ]
            ]);

        });

    });

    describe("->treeify()", function() {

        it("treeify schema paths", function() {

            expect($this->schema->treeify(['gallery', 'tags']))->toBe([
                'gallery' => null,
                'images_tags' => [
                    'tag' => null
                ],
                'tags' => null
            ]);

        });

    });

    describe("->cast()", function() {

        beforeEach(function() {

            $handlers = [
                'string' => function($value, $options = []) {
                    return (string) $value;
                },
                'integer' => function($value, $options = []) {
                    return (integer) $value;
                },
                'float'   => function($value, $options = []) {
                    return (float) $value;
                },
                'decimal' => function($value, $options = []) {
                    $options += ['precision' => 2];
                    return (float) number_format($value, $options['precision']);
                },
                'boolean' => function($value, $options = []) {
                    return !!$value;
                },
                'date'    => function($value, $options = []) {
                    return $this->format('cast', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime'    => function($value, $options = []) {
                    $options += ['format' => 'Y-m-d H:i:s'];
                    if (is_numeric($value)) {
                        return new DateTime('@' . $value);
                    }
                    if ($value instanceof DateTime) {
                        return $value;
                    }
                    return DateTime::createFromFormat($options['format'], date($options['format'], strtotime($value)));
                },
                'null'    => function($value, $options = []) {
                    return null;
                }
            ];

            $this->schema->formatter('cast', 'id',        $handlers['integer']);
            $this->schema->formatter('cast', 'serial',    $handlers['integer']);
            $this->schema->formatter('cast', 'integer',   $handlers['integer']);
            $this->schema->formatter('cast', 'float',     $handlers['float']);
            $this->schema->formatter('cast', 'decimal',   $handlers['decimal']);
            $this->schema->formatter('cast', 'date',      $handlers['date']);
            $this->schema->formatter('cast', 'datetime',  $handlers['datetime']);
            $this->schema->formatter('cast', 'boolean',   $handlers['boolean']);
            $this->schema->formatter('cast', 'null',      $handlers['null']);
            $this->schema->formatter('cast', 'string',    $handlers['string']);
            $this->schema->formatter('cast', '_default_', $handlers['string']);

        });

        it("casts a nested entity data", function() {

            $image = $this->schema->cast(null, [
                'id'         => '1',
                'gallery_id' => '2',
                'name'       => 'image.jpg',
                'title'      => 'My Image',
                'score'      => '8.9',
                'tags'       => [
                    [
                        'id'   => '1',
                        'name' => 'landscape'
                    ],
                    [
                        'id'   => '2',
                        'name' => 'mountain'
                    ]
                ]
            ]);

            expect($image->id)->toBe(1);
            expect($image->gallery_id)->toBe(2);
            expect($image->name)->toBe('image.jpg');
            expect($image->title)->toBe('My Image');
            expect($image->score)->toBe(8.9);
            expect($image->tags)->toBeAnInstanceOf('Chaos\Collection\Through');
            expect($image->tags->schema())->toBe(Tag::definition());
            expect($image->tags[0]->data())->toEqual(['id' => '1', 'name' => 'landscape']);
            expect($image->tags[1]->data())->toEqual(['id' => '2', 'name' => 'mountain']);

        });

    });

    describe(".format()", function() {

        beforeEach(function() {
            $this->schema = new Schema();
            $this->schema->column('id',         ['type' => 'serial']);
            $this->schema->column('name',       ['type' => 'string']);
            $this->schema->column('null',       ['type' => 'string']);
            $this->schema->column('value',      ['type' => 'integer']);
            $this->schema->column('double',     ['type' => 'float']);
            $this->schema->column('revenue',    [
                'type'      => 'decimal',
                'length'    =>  20,
                'precision' =>  2
            ]);
            $this->schema->column('active',     ['type' => 'boolean']);
            $this->schema->column('registered', ['type' => 'date']);
            $this->schema->column('created',    ['type' => 'datetime']);
        });

        it("formats according default `'cast'` handlers", function() {

            expect($this->schema->format('cast', 'id', 123))->toBe(123);
            expect($this->schema->format('cast', 'value', 123))->toBe(123);
            expect($this->schema->format('cast', 'double', 12.3))->toBe(12.3);
            expect($this->schema->format('cast', 'revenue', 12.3))->toBe('12.30');
            $date = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 00:00:00');
            expect($this->schema->format('cast', 'registered', $date))->toEqual($date);
            expect($this->schema->format('cast', 'registered', '2014-11-21'))->toEqual($date);
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->schema->format('cast', 'created', $datetime))->toEqual($datetime);
            expect($this->schema->format('cast', 'created', '2014-11-21 10:20:45'))->toEqual($datetime);
            expect($this->schema->format('cast', 'active', true))->toBe(true);
            expect($this->schema->format('cast', 'active', false))->toBe(false);
            expect($this->schema->format('cast', 'null', null))->toBe(null);
            expect($this->schema->format('cast', 'name', 'abc'))->toBe('abc');
            expect($this->schema->format('cast', 'unexisting', 123))->toBe(123);

        });

        it("formats according default `'array'` handlers", function() {

            expect($this->schema->format('array', 'id', 123))->toBe(123);
            expect($this->schema->format('array', 'value', 123))->toBe(123);
            expect($this->schema->format('array', 'double', 12.3))->toBe(12.3);
            expect($this->schema->format('array', 'revenue', 12.3))->toBe('12.3');
            $date = DateTime::createFromFormat('Y-m-d', '2014-11-21');
            expect($this->schema->format('array', 'registered', $date))->toBe('2014-11-21');
            expect($this->schema->format('array', 'registered', '2014-11-21'))->toBe('2014-11-21');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45');
            expect($this->schema->format('array', 'created', $datetime))->toBe('2014-11-21 10:20:45');
            expect($this->schema->format('array', 'created', '2014-11-21 10:20:45'))->toBe('2014-11-21 10:20:45');
            expect($this->schema->format('array', 'active', true))->toBe(true);
            expect($this->schema->format('array', 'active', false))->toBe(false);
            expect($this->schema->format('array', 'null', null))->toBe(null);
            expect($this->schema->format('array', 'name', 'abc'))->toBe('abc');
            expect($this->schema->format('array', 'unexisting', 123))->toBe('123');

        });

    });

});
