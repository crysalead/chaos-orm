<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Chaos\ORM\Schema;
use Chaos\ORM\Model;
use Chaos\ORM\Document;
use Chaos\ORM\Collection\Collection;

use Kahlan\Plugin\Double;

use Chaos\ORM\Spec\Fixture\Model\Gallery;
use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Spec\Fixture\Model\ImageTag;
use Chaos\ORM\Spec\Fixture\Model\Tag;

describe("Schema", function() {

    beforeEach(function() {
        $this->schema = Image::definition();

        $this->preferences = new Schema();
        $this->preferences->column('preferences', ['type' => 'object', 'default' => []]);
        $this->preferences->column('preferences.blacklist', ['type' => 'object', 'default' => []]);
        $this->preferences->column('preferences.blacklist.projects', ['type' => 'id', 'array' => true, 'default' => []]);
        $this->preferences->column('preferences.mail', ['type' => 'object', 'default' => []]);
        $this->preferences->column('preferences.mail.enabled', ['type' => 'boolean', 'default' => true]);
        $this->preferences->column('preferences.mail.frequency', ['type' => 'integer', 'default' => 24]);
    });

    afterEach(function() {
        Image::reset();
    });

    describe("->__construct()", function() {

        it("correctly sets config options", function() {

            $conventions = Double::instance();

            $schema = new Schema([
                'source'      => 'image',
                'class'       => Image::class,
                'key'         => 'key',
                'locked'      => false,
                'columns'     => ['id' => 'serial', 'age' => 'integer'],
                'meta'        => ['some' => ['meta']],
                'conventions' => $conventions
            ]);

            expect($schema->source())->toBe('image');
            expect($schema->reference())->toBe(Image::class);
            expect($schema->key())->toBe('key');
            expect($schema->locked())->toBe(false);
            expect($schema->fields())->toBe(['id', 'age']);
            expect($schema->meta())->toBe(['some' => ['meta']]);
            expect($schema->conventions())->toBe($conventions);

        });

    });

    describe("->source()", function() {

        it("gets/sets the source", function() {

            $schema = new Schema();

            expect($schema->source('source_name'))->toBe($schema);
            expect($schema->source())->toBe('source_name');

        });

    });

    describe("->reference()", function() {

        it("gets/sets the conventions", function() {

            $schema = new Schema();

            expect($schema->reference(Image::class))->toBe($schema);
            expect($schema->reference())->toBe(Image::class);

        });

    });

    describe("->lock()/->locked()", function() {

        it("gets/sets the lock value", function() {

            $schema = new Schema();

            expect($schema->lock(false))->toBe($schema);
            expect($schema->locked())->toBe(false);

            expect($schema->lock())->toBe($schema);
            expect($schema->locked())->toBe(true);

            expect($schema->lock(true))->toBe($schema);
            expect($schema->locked())->toBe(true);

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

        it("returns all column names", function() {

            expect($this->schema->names())->toBe(['id', 'name', 'title', 'score', 'gallery_id']);

        });

        it("returns all column names and nested ones", function() {

          expect($this->preferences->names())->toBe([
            'preferences',
            'preferences.blacklist',
            'preferences.blacklist.projects',
            'preferences.mail',
            'preferences.mail.enabled',
            'preferences.mail.frequency'
          ]);

        });

        it("filters out virtual fields", function() {

            $this->schema->column('virtualField', ['virtual' => true]);
            $fields = $this->schema->names();
            expect(isset($fields['virtualField']))->toBe(false);

        });

    });

    describe("->fields()", function() {

        it("returns all fields", function() {

            expect($this->schema->fields())->toBe(['id', 'name', 'title', 'score', 'gallery_id']);

        });

        it("returns fields according the base path", function() {

            expect($this->preferences->fields())->toBe(['preferences']);
            expect($this->preferences->fields('preferences'))->toBe(['blacklist', 'mail']);
            expect($this->preferences->fields('preferences.mail'))->toBe(['enabled', 'frequency']);

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
                'name' => [
                    'type'    => 'string',
                    'array'   => false,
                    'null'    => false
                ],
                'title' => [
                    'type'    => 'string',
                    'length'  => 50,
                    'array'   => false,
                    'null'    => false
                ],
                'score' => [
                    'type'    => 'float',
                    'array'   => false,
                    'null'    => false
                ],
                'gallery_id' => [
                    'type'  => 'id',
                    'array' => false,
                    'null'  => true
                ]
            ]);

        });

    });

    describe("->defaults()", function() {

        it("returns defaults", function() {

            $this->schema->column('name', ['type' => 'string', 'default' => 'Enter The Name Here']);
            $this->schema->column('title', ['type' => 'string', 'default' => 'Enter The Title Here', 'length' => 50]);

            expect($this->schema->defaults())->toBe([
                'name'       => 'Enter The Name Here',
                'title'      => 'Enter The Title Here'
            ]);

        });

        it("correctly sets default values with stars", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object', 'default' => []]);
            $schema->column('data.*', ['type' => 'object', 'default' => []]);
            $schema->column('data.*.checked', ['type' => 'boolean', 'default' => true]);
            $schema->locked(true);

            $document = new Document(['schema' => $schema]);

            expect($document->get('data.value1.checked'))->toBe(true);

        });

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
                'type'  => 'id',
                'array' => false,
                'null'  => true
            ]);

            expect($schema->column('name'))->toBe([
                'type'  => 'string',
                'array' => false,
                'null'  => false
            ]);

            expect($schema->column('title'))->toBe([
                'type'  => 'string',
                'length'  => 50,
                'array' => false,
                'null'  => false
            ]);

            expect($schema->column('score'))->toBe([
                'type'  => 'float',
                'array' => false,
                'null'  => false
            ]);

        });

        it("sets a field with a specific type", function() {

            $this->schema->column('age', ['type' => 'integer']);
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => false
            ]);

        });

        it("sets a field with a specific type using the array syntax", function() {

            $this->schema->column('age', ['integer']);
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => false
            ]);

        });

        it("sets a field with a specific type using the string syntax", function() {

            $this->schema->column('age', 'integer');
            expect($this->schema->column('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => false
            ]);

        });

        it("sets a field as an array", function() {

            $this->schema->column('ids', ['type' => 'integer', 'array' => true]);
            expect($this->schema->column('ids'))->toBe([
                'type' => 'integer',
                'array' => true,
                'null' => false
            ]);

        });

        it("sets a field with custom options", function() {

            $this->schema->column('name', ['type' => 'integer', 'length' => 11, 'use' => 'bigint']);
            expect($this->schema->column('name'))->toBe([
                'type'   => 'integer',
                'length' => 11,
                'use'    => 'bigint',
                'array'  => false,
                'null'   => false
            ]);

        });

        it("sets nested fields", function() {

            $document = $this->preferences->cast(null, []);

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

            context("with a dynamic getter and a dynamic setter", function() {

                beforeEach(function() {

                    $this->schema = new Schema(['locked' => false]);
                    $this->schema->column('hello', [
                        'type' => 'string',
                        'getter' => function($entity, $data, $name) {
                            return 'world';
                        },
                        'setter' => function($entity, $data, $name) {
                            return $entity->set('amiga', 1200);
                        }
                    ]);

                });

                it("builds the field", function() {

                    $document = $this->schema->cast();
                    expect($document->get('hello'))->toBe('world');

                });

                it("doesn't call the setter", function() {

                    $document = $this->schema->cast();
                    expect($document->get('hello'))->toBe('world');
                    expect($document->get('amiga'))->toBe(null);

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
                    expect(isset($document->datetime))->toBe(true);

                });

                it("rebuilds the field on changes", function() {

                    $document = $this->schema->cast();
                    $document->datetime = '2015-05-20 21:50:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('21:50:00');

                    $document->datetime = '2015-05-20 22:15:00';
                    expect($document->date)->toBe('2015-05-20');
                    expect($document->time)->toBe('22:15:00');
                    expect(isset($document->datetime))->toBe(true);

                });

            });

        });

    });

    describe("->unset()", function() {

        it("unsets a field", function() {

            $this->schema->unset('title');
            expect($this->schema->has('title'))->toBe(false);

        });

    });

    describe("->has()", function() {

        it("checks if a schema contain a field name", function() {

            expect($this->schema->has('title'))->toBe(true);
            $this->schema->unset('title');
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

                $this->schema->lock(false);

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

            $schema = new Schema();
            $schema->column('virtualField1', ['virtual' => true]);
            $schema->column('virtualField2', ['virtual' => true]);

            expect($schema->virtuals())->toBe(['virtualField1', 'virtualField2']);

        });

    });

    describe("->isVirtual()", function() {

        it("checks virtual fields", function() {

            $schema = new Schema();
            $schema->column('virtualField1', ['virtual' => true]);
            $schema->column('virtualField2', ['virtual' => true]);
            $schema->column('field3', []);

            expect($schema->isVirtual('virtualField1'))->toBe(true);
            expect($schema->isVirtual('virtualField2'))->toBe(true);
            expect($schema->isVirtual(['virtualField1', 'virtualField2']))->toBe(true);
            expect($schema->isVirtual(['virtualField1', 'field3']))->toBe(false);

        });

    });

    describe("->isPrivate()", function() {

        it("checks private fields", function() {

            $schema = new Schema();
            $schema->column('privateField1', ['private' => true]);
            $schema->column('privateField2', ['private' => true]);
            $schema->column('field3', []);

            expect($schema->isPrivate('privateField1'))->toBe(true);
            expect($schema->isPrivate('privateField2'))->toBe(true);
            expect($schema->isPrivate(['privateField1', 'privateField2']))->toBe(true);
            expect($schema->isPrivate(['privateField1', 'fField3']))->toBe(false);

        });

        it("filter private fields out from exports", function() {
            $schema = new Schema();
            $schema->column('privateField1', ['private' => true]);
            $schema->column('privateField2', ['private' => true]);
            $schema->column('field3', []);

            $document = Document::create([
                'privateField1' => 'privatedata1',
                'privateField2' => 'privatedata2',
                'field3' => 'hello'
            ], ['schema' => $schema]);

            expect($document->data())->toBe([
                'field3' => 'hello'
            ]);

        });

    });

    describe("->bind()", function() {

        it("binds a `belongsTo` relation", function() {

            expect($this->schema->hasRelation('parent'))->toBe(false);

            $this->schema->bind('parent', [
                'relation' => 'belongsTo',
                'to'       => Image::class,
                'keys'     => ['image_id' => 'id']
            ]);

            expect($this->schema->hasRelation('parent'))->toBe(true);

            $foreignKey = $this->schema->conventions()->apply('reference', 'parent');

            $column = $this->schema->column($foreignKey);
            expect($column)->toBe([
                'type' => 'id',
                'array' => false,
                'null' => false
            ]);

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

            $model = Double::classname(['extends' => Model::class]);

            $schema = new Schema(['class' => $model]);
            $schema->column('embedded', [
                'type'  => 'object',
                'class' => $model
            ]);

            expect($schema->relations())->toBe([]);
            expect($schema->relations(true))->toBe(['embedded']);

        });

    });

    describe("->conventions()", function() {

        it("gets/sets the conventions", function() {

            $conventions = Double::instance();
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

        it("expands HasManyThrough relations", function() {

            expect($this->schema->treeify(['gallery', 'tags']))->toBe([
                'gallery' => null,
                'images_tags' => [
                    'embed' => [
                        'tag' => null
                    ]
                ],
                'tags' => null
            ]);

        });

        it("expands dotted relations", function() {

            expect($this->schema->treeify(['gallery', 'images_tags.tag']))->toEqual([
                'gallery' => null,
                'images_tags' => [
                    'embed' => [
                        'tag' => null
                    ]
                ]
            ]);

        });

        it("expands dotted relations with conditions", function() {

            expect($this->schema->treeify([
              'gallery',
              'images_tags.tag' => ['conditions' => ['name' => 'computer']]
            ]))->toEqual([
                'gallery' => null,
                'images_tags' => [
                    'embed' => [
                        'tag' => [
                            'conditions' => [
                                'name' => 'computer'
                            ]
                        ]
                    ]
                ]
            ]);

        });

        it("works in a indempotent way", function() {

            expect($this->schema->treeify([
                'gallery' => ['conditions' => ['name' => 'Foo']],
                'images_tags' => [
                    'embed' => ['tag']
                ]
            ]))->toEqual([
                'gallery' => ['conditions' => ['name' => 'Foo']],
                'images_tags' => [
                    'embed' => ['tag']
                ]
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
            ], [ 'exists' => false ]);

            expect($image->id)->toBe(1);
            expect($image->gallery_id)->toBe(2);
            expect($image->name)->toBe('image.jpg');
            expect($image->title)->toBe('My Image');
            expect($image->score)->toBe(8.9);
            expect($image->tags)->toBeAnInstanceOf('Chaos\ORM\Collection\Through');
            expect($image->tags->schema())->toBe(Tag::definition());
            expect($image->tags[0]->data())->toEqual(['id' => '1', 'name' => 'landscape']);
            expect($image->tags[1]->data())->toEqual(['id' => '2', 'name' => 'mountain']);

        });

        it("casts arrays of integer", function() {

            $schema = new Schema();
            $schema->column('list', ['type' => 'integer', 'array' => true]);

            $document = new Document(['schema' => $schema]);
            $document->set('list', [4, 5]);

            expect($document->get('list')->count())->toBe(2);
            expect($document->get('list')->data())->toEqual([4, 5]);

        });

        it("casts field name with stars", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object']);
            $schema->column('data.*', ['type' => 'object']);
            $schema->column('data.*.checked', ['type' => 'boolean']);
            $schema->column('data.*.test', ['type' => 'object']);
            $schema->column('data.*.test.*', ['type' => 'object']);
            $schema->column('data.*.test.*.nested', ['type' => 'object']);
            $schema->column('data.*.test.*.nested.*', ['type' => 'boolean', 'array' => true]);
            $schema->lock(true);

            $document = new Document(['schema' => $schema]);

            $document->set('data.value1.checked', true);
            $document->set('data.value2.checked', 1);
            expect($document->get('data.value1.checked'))->toBe(true);
            expect($document->get('data.value2.checked'))->toBe(true);

            $document->set('data.value3.checked', false);
            $document->set('data.value4.checked', 0);
            $document->set('data.value5.checked', '');
            expect($document->get('data.value3.checked'))->toBe(false);
            expect($document->get('data.value4.checked'))->toBe(false);
            expect($document->get('data.value5.checked'))->toBe(false);

            $document->set('data.value3.test.deeply.nested.false', [0, '', false]);
            $document->set('data.value3.test.deeply.nested.true', [1, true]);
            expect($document->get('data.value3.test.deeply.nested.false')->data())->toEqual([false, false, false]);
            expect($document->get('data.value3.test.deeply.nested.true')->data())->toEqual([true, true]);

            expect($document->data())->toEqual([
                'data' => [
                    'value1' => [
                        'checked' => true
                    ],
                    'value2' => [
                        'checked' => true
                    ],
                    'value3' => [
                        'checked' => false,
                        'test' => [
                            'deeply' => [
                                'nested' => [
                                    'false' => [false, false, false],
                                    'true' => [true, true]
                                ]
                            ]
                        ]
                    ],
                    'value4' => [
                        'checked' => false
                    ],
                    'value5' => [
                        'checked' => false
                    ]
                ]
            ]);

        });

        it("doesn't cast undefined type", function() {

          $schema = new Schema();
          $schema->column('key', ['type' => 'undefined']);

          $document = new Document(['schema' => $schema]);

          $document->set('key', 'test');
          expect($document->get('key'))->toEqual('test');

          $document->set('key', [4, 5]);
          expect($document->get('key'))->toEqual([4, 5]);

          $document->set('key', ['a' => 'b']);
          expect($document->get('key'))->toEqual(['a' => 'b']);

        });

        it("support custom collection class", function() {

            $model = Double::classname(['extends' => Model::class]);
            $model::definition()->lock(false);

            $MyCollection = Double::classname(['extends' => Collection::class]);
            $model::classes(['set' => $MyCollection]);

            $schema = new Schema();
            $schema->column('collection', ['type' => 'object', 'class' => $model, 'array' => true]);

            $data = [
                ['id' => '1', 'title' => 'Amiga 1200'],
                ['id' => '2', 'title' => 'Las Vegas']
            ];

            $document = new Document(['schema' => $schema]);
            $document['collection'] = $data;

            expect($document['collection'])->toBeAnInstanceOf($MyCollection);

        });

        it("correctly sets base path with stars", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object', 'default' => []]);
            $schema->column('data.*', ['type' => 'object', 'default' => []]);
            $schema->column('data.*.checked', ['type' => 'boolean']);
            $schema->column('data.*.test', ['type' => 'object', 'default' => []]);
            $schema->column('data.*.test.*', ['type' => 'object', 'default' => []]);
            $schema->column('data.*.test.*.nested', ['type' => 'object', 'default' => []]);
            $schema->column('data.*.test.*.nested.*', ['type' => 'boolean', 'array' => true]);
            $schema->locked(true);

            $document = new Document(['schema' => $schema]);

            expect($document->get('data')->basePath())->toBe('data');
            expect($document->get('data.value1')->basePath())->toBe('data.*');
            expect($document->get('data.value3.test.deeply.nested')->basePath())->toBe('data.*.test.*.nested');

        });

    });

    describe("->format()", function() {

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
            $this->schema->column('json',       ['type' => 'json']);
        });

        it("formats according default `'array'` handlers", function() {

            expect($this->schema->format('array', 'value', 123))->toBe(123);
            expect($this->schema->format('array', 'double', 12.3))->toBe(12.3);
            expect($this->schema->format('array', 'revenue', 12.3))->toBe('12.3');
            $date = DateTime::createFromFormat('Y-m-d', '2014-11-21', new DateTimeZone('UTC'));
            expect($this->schema->format('array', 'registered', $date))->toBe('2014-11-21');
            expect($this->schema->format('array', 'registered', '2014-11-21'))->toBe('2014-11-21');
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45', new DateTimeZone('UTC'));
            expect($this->schema->format('array', 'created', $datetime))->toBe('2014-11-21 10:20:45');
            expect($this->schema->format('array', 'created', '2014-11-21 10:20:45'))->toBe('2014-11-21 10:20:45');
            expect($this->schema->format('array', 'active', true))->toBe(true);
            expect($this->schema->format('array', 'active', false))->toBe(false);
            expect($this->schema->format('array', 'null', null))->toBe(null);
            expect($this->schema->format('array', 'name', 'abc'))->toBe('abc');
            expect($this->schema->format('array', 'unexisting', 123))->toBe(123);
            expect($this->schema->format('array', 'json', new Collection(['data' => [1,2]])))->toBe([1,2]);

        });

        it("formats udefined fields using the `'_default_'` formatter when present", function() {

            expect($this->schema->format('array', 'unexisting', 123))->toBe(123);

            $this->schema->formatter('array', '_default_', function($value) {
                return (string) $value;
            });

            expect($this->schema->format('array', 'unexisting', 123))->toBe('123');

        });

        it("throws an InvalidArgumentException for `'cast'` handlers", function() {

            $closure = function() {
                $this->schema->format('cast', 'value', 123);
            };

            expect($closure)->toThrow(new InvalidArgumentException("Use `Schema::cast()` to perform casting."));

        });

    });

    describe("->convert()", function() {

        it("formats according default `'cast'` handlers", function() {

            expect($this->schema->convert('cast', 'integer', 123))->toBe(123);
            expect($this->schema->convert('cast', 'float', 12.3))->toBe(12.3);
            expect($this->schema->convert('cast', 'decimal', 12.3, ['length' =>  20, 'precision' =>  2]))->toBe('12.30');
            $date = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 00:00:00', new DateTimeZone('UTC'));
            expect($this->schema->convert('cast', 'date', $date))->toEqual($date);
            expect($this->schema->convert('cast', 'date', '2014-11-21'))->toEqual($date);
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', '2014-11-21 10:20:45', new DateTimeZone('UTC'));
            expect($this->schema->convert('cast', 'datetime', $datetime))->toEqual($datetime);
            expect($this->schema->convert('cast', 'datetime', '2014-11-21 10:20:45'))->toEqual($datetime);
            expect($this->schema->convert('cast', 'datetime', 'abcd'))->toEqual(null);
            expect($this->schema->convert('cast', 'boolean', true))->toBe(true);
            expect($this->schema->convert('cast', 'boolean', false))->toBe(false);
            expect($this->schema->convert('cast', 'null', null))->toBe(null);
            expect($this->schema->convert('cast', 'string', 'abc'))->toBe('abc');
            expect($this->schema->convert('cast', 'unexisting', 123))->toBe(123);
            expect($this->schema->convert('cast', 'json', '[1,2]'))->toBe([1,2]);

        });

    });

    describe("->hasRelation()", function() {

        it("checks if an embedded relation exists", function() {

            $schema = new Schema();
            $schema->column('embedded', ['type' => 'object']);

            expect($schema->hasRelation('embedded'))->toBe(true);
            expect($schema->hasRelation('embedded', true))->toBe(true);
            expect($schema->hasRelation('embedded', false))->toBe(false);

        });

        it("checks if an external relation exists", function() {

            $schema = new Schema();
            $schema->bind('external', [
                'relation' => 'belongsTo',
                'to'       => Image::class,
                'keys'     => ['image_id' => 'id']
            ]);

            expect($schema->hasRelation('external'))->toBe(true);
            expect($schema->hasRelation('external', false))->toBe(true);
            expect($schema->hasRelation('external', true))->toBe(false);

        });

    });

    describe("->persist()", function() {

        beforeEach(function() {
            $this->schema = new Schema(['locked' => false]);
            $this->data = [];
            $data = &$this->data;
            $this->filter = null;
            $filter = &$this->filter;
            allow($this->schema)->toReceive('bulkInsert')->andRun(function ($inserts, $closure) use (&$data, &$filter) {
                $data['inserts'] = $inserts;
                $filter = $closure;

            });
            allow($this->schema)->toReceive('bulkUpdate')->andRun(function ($updates, $closure) use (&$data, &$filter) {
                $data['updates'] = $updates;
                $filter = $closure;
            });
        });

        it("filters out virtual values", function() {

            $this->schema->column('a', ['type' => 'string', 'virtual' => true]);

            $entity = Model::create([], ['schema' => $this->schema]);

            $entity['a'] = 1;
            $entity['b'] = 2;
            expect($entity->get('a'))->toBe('1');
            expect($entity->get('b'))->toBe(2);

            $this->schema->persist($entity);

            expect($this->data['inserts'])->toBe([$entity]);
            expect($this->filter($entity))->toBe(['b' => 2]);

        });

    });

});
