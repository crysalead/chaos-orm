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

use Kahlan\Plugin\Stub;

describe("Document", function() {

    describe("->__construct()", function() {

        it("loads the data", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $document = new Document(['data' => [
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]]);
            expect($document->title)->toBe('Hello');
            expect($document->body)->toBe('World');
            expect($document->created)->toBe($date);
            expect($document)->toHaveLength(3);

        });

    });

    describe("->self()", function() {

        it("returns the document class name", function() {

            $document = new Document();
            expect($document->self())->toBe(Document::class);

        });

    });

    describe("->parents()", function() {

        it("gets the parents", function() {

            $parent = new Document();
            $document = new Document();
            $parent->value = $document;
            expect($document->parents()->has($parent))->toBe(true);
            expect($document->parents()->get($parent))->toBe('value');

        });
    });

    describe("->unsetParent()", function() {

        it("unsets a parent", function() {

            $parent = new Document();
            $document = new Document();
            $parent->value = $document;
            unset($parent->value);
            expect($document->parents()->has($parent))->toBe(false);

        });

    });

    describe("->disconnect()", function() {

        it("removes a document from its graph", function() {

            $parent = new Document();
            $document = new Document();
            $parent->value = $document;
            $document->disconnect();
            expect($document->parents()->has($parent))->toBe(false);
            expect($parent->has('value'))->toBe(false);

        });

    });

    describe("->basePath()", function() {

        it("returns the root path", function() {

            $document = new Document(['basePath' => 'items']);
            expect($document->basePath())->toBe('items');

        });

    });

    describe("->get()", function() {

        it("gets a value", function() {

            $document = new Document();
            expect($document->set('title', 'Hello'))->toBe($document);
            expect($document->get('title'))->toBe('Hello');

        });

        it("gets a virtual value", function() {

            $schema = new Schema();
            $schema->column('a', ['type' => 'string', 'virtual' => true]);

            $document = new Document(['schema' => $schema]);

            $document['a'] = 1;
            expect($document->get('a'))->toBe('1');

        });

        it("gets all values", function() {

            $document = new Document();

            $document->set([
                'a' => 1,
                'b' => 2,
                'c' => 3
            ]);

            expect($document->get())->toBe([
                'a' => 1,
                'b' => 2,
                'c' => 3
            ]);

        });

        it("returns `null` for undefined field", function() {

            $document = new Document();
            expect($document->get('value'))->toBe(null);
            expect($document->get('nested.value'))->toBe(null);

        });

        it("throws an error for undefined field with locked schema", function() {

            $closure = function() {
                $schema = new Schema();
                $document = new Document(['schema' => $schema]);
                $document->set('value', 'something');
                $document->get('value');
            };

            expect($closure)->toThrow(new ORMException("Missing schema definition for field: `value`."));

        });

        it("throws an error when the path is invalid", function() {

            $closure = function() {
              $document = new Document();
              $document->set('value', 'Hello World');
              $document->get('value.invalid');
            };

            expect($closure)->toThrow(new ORMException("The field: `value` is not a valid document or entity."));

        });

    });

    describe("->set()", function() {

        it("sets values", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $document = new Document();
            expect($document->set('title', 'Hello'))->toBe($document);
            expect($document->set('body', 'World'))->toBe($document);
            expect($document->set('created', $date))->toBe($document);

            expect($document->title)->toBe('Hello');
            expect($document->body)->toBe('World');
            expect($document->created)->toBe($date);
            expect($document)->toHaveLength(3);

        });

        it("sets nested arbitraty value in cascade when locked is `false`", function() {

            $image = new Document();
            $image->set('a.nested.value', 'hello');

            expect($image->data())->toEqual([
                'a' => [
                    'nested' => [
                        'value' => 'hello'
                    ]
                ]
            ]);

        });

        it("returns `null` for undefined fields", function() {

            $document = new Document();
            expect($document->foo)->toBe(null);

        });

        it("sets an array of values", function() {

            $date = new DateTime('2014-10-26 00:25:15');

            $document = new Document();
            expect($document->set([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]))->toBe($document);
            expect($document->title)->toBe('Hello');
            expect($document->body)->toBe('World');
            expect($document->created)->toBe($date);
            expect($document)->toHaveLength(3);

        });

        it("returns all raw datas with no parameter", function() {

            $date = time();
            $document = new Document([
                'data' => [
                    'title'   => 'Hello',
                    'body'    => 'World',
                    'created' => $date
                ]
            ]);
            expect($document->get())->toBe([
                'title'   => 'Hello',
                'body'    => 'World',
                'created' => $date
            ]);

        });

        it("sets and gets the 0 field name", function() {

            $document = new Document();
            $document->set('0', 'zero');
            expect($document->get('0'))->toBe('zero');

            $document->set(0, 'zero');
            expect($document->get(0))->toBe('zero');

        });

        it("throws an exception if the field name is not valid", function() {

            $closure = function() {
                $document = new Document();
                $document->get('');
            };
            expect($closure)->toThrow(new ORMException("Field name can't be empty."));

        });

        it("correctly sets parents", function() {

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

            expect($document->parents()->count())->toBe(0);
            expect($document->get('data')->parents()->has($document))->toBe(true);
            expect($document->get('data.value1')->parents()->has($document->get('data')))->toBe(true);
            expect($document->get('data.value1.test')->parents()->has($document->get('data')))->toBe(false);
            expect($document->get('data.value1.test')->parents()->has($document->get('data.value1')))->toBe(true);
            expect($document->get('data.value3.test.deeply.nested')->parents()->has($document->get('data.value3.test.deeply')))->toBe(true);

        });

        it("sets documents by references", function() {

            $document1 = new Document();
            $document2 = new Document();

            $document1->set('data.value1.test', true);
            $document2->set('data', $document1->get('data'));

            expect($document1->get('data'))->toBe($document2->get('data'));

            $document2->set('data.value1.test', false);

            expect($document1->get('data.value1.test'))->toBe(false);
            expect($document2->get('data.value1.test'))->toBe(false);

        });

        it("casts objects according JSON casting handlers", function() {

            $schema = new Schema();
            $schema->column('holidays', ['type' => 'string', 'array' => true]);

            $document = new Document(['schema' => $schema]);
            $holidays = [
                'allSaintsDay',
                'armisticeDay',
                'ascensionDay',
                'assumptionOfMary',
                'bastilleDay',
                'christmasDay',
                'easterMonday',
                'internationalWorkersDay',
                'newYearsDay',
                'pentecostMonday',
                'victoryInEuropeDay'
            ];
            $document->holidays = $holidays;
            expect($document->holidays->data())->toEqual($holidays);

        });

        it("casts array of objects according JSON casting handlers", function() {

            $schema = new Schema();
            $schema->column('events', ['type' => 'object', 'array' => true]);
            $schema->column('events.from', ['type' => 'string']);
            $schema->column('events.to', ['type' => 'string']);

            $document = new Document(['schema' => $schema]);
            $events = [
                ['from' => '08:00', 'to' =>  '10:00'],
                ['from' => '12:00', 'to' =>  '16:00']
            ];
            $document->events = $events;
            expect($document->events[0])->toBeAnInstanceOf(Document::class);
            expect($document->events[1])->toBeAnInstanceOf(Document::class);
            expect($document->events->data())->toEqual($events);

        });

        it("casts array of custom objects according JSON casting handlers", function() {

            $event = new class extends Document {
                protected static function _define($schema) {
                    $schema->column('from', ['type' => 'string']);
                    $schema->column('to', ['type' => 'string']);
                }
            };
            $Event = get_class($event);

            $schema = new Schema();
            $schema->column('events', ['type' => 'object', 'array' => true, 'class' => $Event ]);

            $document = new Document(['schema' => $schema]);
            $events = [
                ['from' => '08:00', 'to' =>  '10:00'],
                ['from' => '12:00', 'to' =>  '16:00']
            ];
            $document->events = $events;
            expect($document->events[0])->toBeAnInstanceOf($Event);
            expect($document->events[1])->toBeAnInstanceOf($Event);
            expect($document->events->data())->toEqual($events);

        });

        it("amends passed data", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object']);
            $schema->column('data.*', ['type' => 'object']);
            $schema->column('data.*.count', ['type' => 'integer']);
            $schema->column('data.*.value', ['type' => 'integer']);

            $document = new Document(['schema' => $schema]);
            $document->set('data', [
                'test' => ['count' => 5, 'value' => 5]
            ]);
            expect($document->get('data.test.count'))->toBe(5);

            $document->amend([
                'data' => [
                    'test' => [
                        'count' => 10, 'value' => 10
                    ]
                ]
            ]);

            expect($document->get('data.test.count'))->toBe(10);

        });

        it("casts data in nested array", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object', 'array' => true]);
            $schema->column('data.count', ['type' => 'integer']);
            $schema->column('data.value', ['type' => 'integer']);

            $document = new Document(['schema' => $schema]);
            $data = [
                ['count' => '09', 'value' => 5]
            ];
            $document->data = $data;
            expect($document->data[0]->count)->toBe(9);

        });

        it("casts data in nested object", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object']);
            $schema->column('data.*', ['type' => 'object']);
            $schema->column('data.*.count', ['type' => 'integer']);
            $schema->column('data.*.value', ['type' => 'integer']);

            $document = new Document(['schema' => $schema]);
            $data = [
                'test' => ['count' => '09', 'value' => 5]
            ];
            $document->data = $data;
            expect($document->data->test->count)->toBe(9);

        });

    });

    describe("->__set()", function() {

        it("sets value", function() {

            $document = new Document();
            $document->hello = 'world';
            expect($document->hello)->toBe('world');

        });

    });

    describe("->__get()", function() {

        it("gets value", function() {

            $document = new Document();
            $document->hello = 'world';
            expect($document->hello)->toBe('world');

        });

    });

    describe("->offsetExists()", function() {

        it("returns true if a element exist", function() {

            $document = new Document();
            $document['field1'] = 'foo';
            $document['field2'] = null;

            expect(isset($document['field1']))->toBe(true);
            expect(isset($document['field2']))->toBe(true);

        });

        it("returns `true` if a element has been setted using a dotted notation", function() {

            $document = new Document();
            $document->set('field1.field1', 'foo');
            $document->set('field2.field2', null);

            expect(isset($document['field1.field1']))->toBe(true);
            expect(isset($document['field2.field2']))->toBe(true);

        });

        it("returns false if a element doesn't exist", function() {

            $document = new Document();
            expect(isset($document['undefined']))->toBe(false);

        });

    });

    describe("->offsetSet/offsetGet()", function() {

        it("allows array access", function() {

            $document = new Document();
            $document['field1'] = 'foo';
            expect($document['field1'])->toBe('foo');
            expect($document)->toHaveLength(1);

        });

        it("sets at a specific key", function() {

            $document = new Document();
            $document['mykey'] = 'foo';
            expect($document['mykey'])->toBe('foo');
            expect($document)->toHaveLength(1);

        });

        it("throws an exception for invalid key", function() {
            $closure = function() {
                $document = new Document();
                $document[] = 'foo';
            };
            expect($closure)->toThrow(new ORMException("Field name can't be empty."));

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

            $document = new Document(['data' => $data]);
            unset($document['body']);
            unset($document['enabled']);

            expect($document)->toHaveLength(2);
            expect($document->data())->toBe([
                'id'    => 1,
                'title' => 'test record'
            ]);

        });

        it("unsets items using a dotted notation", function() {

            $document = new Document();
            $document->set('field1.field1', 'foo');
            $document->set('field2.field2', null);
            unset($document['field1.field1']);
            unset($document['field2.field2']);

            expect(isset($document['field1.field1']))->toBe(false);
            expect(isset($document['field2.field2']))->toBe(false);

        });

        it("unsets all items in a foreach", function() {

            $data = [
                'field1' => 'Delete me',
                'field2' => 'Delete me'
            ];

            $document = new Document(['data' => $data]);

            foreach ($document as $i => $word) {
                unset($document[$i]);
            }

            expect($document->data())->toBe([]);

        });

        it("unsets last items in a foreach", function() {

            $data = [
                'field1' => 'Hello',
                'field2' => 'Hello again!',
                'field3' => 'Delete me'
            ];

            $document = new Document(['data' => $data]);

            foreach ($document as $i => $word) {
                if ($word === 'Delete me') {
                    unset($document[$i]);
                }
            }

            expect($document->data())->toBe([
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

            $document = new Document(['data' => $data]);

            foreach ($document as $i => $word) {
                if ($word === 'Delete me') {
                    unset($document[$i]);
                }
            }

            expect($document->data())->toBe([
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

            $document = new Document(['data' => $data]);

            $loop = 0;
            foreach ($document as $i => $word) {
                if ($word === 'Delete me') {
                    unset($document[$i]);
                }
                $loop++;
            }

            expect($loop)->toBe(4);

            expect($document->data())->toBe([
                'field2' => 'Hello',
                'field4' => 'Hello again!'
            ]);

        });

    });

    describe("->has()", function() {

        it("delegates to `offsetExists`", function() {

            $document = new Document();
            expect($document)->toReceive('offsetExists')->with(0);
            $document->has(0);

        });

    });

    describe("->unset()", function() {

        it("delegates to `offsetUnset`", function() {

            $document = new Document();
            expect($document)->toReceive('offsetUnset')->with(0);
            $document->unset(0);

        });

    });

    describe("->original()", function() {

        it("returns original data", function() {

            $document = new Document([
                'data' => [
                    'id'    => 1,
                    'title' => 'Hello',
                    'body'  => 'World'
                ]
            ]);

            $document->set([
                'id'    => 1,
                'title' => 'Good Bye',
                'body'  => 'Folks'
            ]);

            expect($document->original('title'))->toBe('Hello');
            expect($document->original('body'))->toBe('World');

            expect($document->title)->toBe('Good Bye');
            expect($document->body)->toBe('Folks');

            expect($document->modified('title'))->toBe(true);
            expect($document->modified('body'))->toBe(true);

        });

        it("returns all original data with no parameter", function() {

            $document = new Document([
                'data' => [
                    'id'     => 1,
                    'title'  => 'Hello',
                    'body'   => 'World'
                ]
            ]);

            $document->set([
                'id'    => 1,
                'title' => 'Good Bye',
                'body'  => 'Folks'
            ]);

            expect($document->original())->toBe([
                'id'    => 1,
                'title' => 'Hello',
                'body'  => 'World'
            ]);

        });

    });

    describe("->modified()", function() {

        it("returns a boolean indicating if a field has been modified", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            $document->title = 'modified';
            expect($document->modified('title'))->toBe(true);

        });

        it("returns `false` if a field has been updated with a same scalar value", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            $document->title = 'original';
            expect($document->modified('title'))->toBe(false);

        });

        it("returns `false` if a field has been updated with a similar object value", function() {

            $document = new Document([
                'data' => [
                    'body' => (object) 'body'
                ]
            ]);

            expect($document->modified('body'))->toBe(false);

            $document->title = (object) 'body';
            expect($document->modified('body'))->toBe(false);

        });

        it("delegates the job for values which has a `modified()` method", function() {

            $childDocument = new Document([
                'data' => [
                    'field' => 'value'
                ]
            ]);

            $document = Document::create(['child' => $childDocument]);

            expect($document->modified())->toBe(false);

            $document->child->field = 'modified';
            expect($document->modified())->toBe(true);

        });

        it("returns `true` when an unexisting field has been added", function() {

            $document = new Document();

            $document->modified = 'modified';

            expect($document->modified())->toBe(true);

        });

        it("returns `true` when a field is unsetted", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            unset($document->title);
            expect($document->modified('title'))->toBe(true);

        });

        it("returns `true` when a field is setted to `null`", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            $document->title = null;

            expect($document->title)->toBe(null);
            expect($document->modified('title'))->toBe(true);

        });

        it("returns `false` when an unexisting field is checked", function() {

            $document = new Document();
            expect($document->modified('unexisting'))->toBe(false);

        });

        it("returns the list of modified fields", function() {

            $document = new Document();

            $document->modified = 'modified';

            expect($document->modified(['return' => true]))->toBe(['modified']);

        });

        it("ignores ignored fields", function() {

            $document = new Document();

            $document->modified = 'modified';

            expect($document->modified(['ignore' => ['modified']]))->toBe(false);

        });

        it("returns `true` when embedded relations data are modified", function() {

            $schema = new Schema();
            $schema->column('list',       ['type' => 'object', 'array' => true]);
            $schema->column('list.value', ['type' => 'integer']);

            $document = new Document([
                'schema' => $schema,
                'data' => ['list' => [
                    ['value' => 50]
                ]]
            ]);

            expect($document->modified())->toBe(false);

            $document->set('list.0.value', 60);
            expect($document->modified())->toBe(true);

        });

    });

    describe("->key()", function() {

        it("returns current key", function() {

            $data = ['field' => 'value'];
            $document = new Document(['data' => $data]);
            $value = $document->key();
            expect($value)->toBe('field');

        });

        it("returns null if non valid", function() {

            $document = new Document();
            $value = $document->key();
            expect($value)->toBe(null);

        });

    });

    describe("->current()", function() {

        it("returns the current value", function() {

            $data = ['field' => 'value'];
            $document = new Document(['data' => $data]);
            $value = $document->current();
            expect($value)->toBe('value');

        });

    });

    describe("->next()", function() {

        it("returns the next value", function() {

            $data = [
                'field1' => 'value1',
                'field2' => 'value2'
            ];

            $document = new Document(['data' => $data]);
            $value = $document->next();
            expect($value)->toBe('value2');

        });

    });

    describe("->prev()", function() {

        it("navigates through collection", function() {

            $data = [
                'id'    => 1,
                'title' => 'test record',
                'body'  => 'test body'
            ];

            $document = new Document(['data' => $data]);

            $document->rewind();
            expect($document->next())->toBe('test record');
            expect($document->next())->toBe('test body');
            expect($document->next())->toBe(null);

            $document->end();
            expect($document->prev())->toBe('test record');
            expect($document->prev())->toBe(1);
            expect($document->prev())->toBe(null);

        });

    });

    describe("->rewind/end()", function() {

        it("returns respectively the first and the last item of the collection", function() {

            $data = [
                'id'    => 1,
                'title' => 'test record',
                'body'  => 'test body'
            ];

            $document = new Document(['data' => $data]);

            expect($document->end())->toBe('test body');
            expect($document->rewind())->toBe(1);
            expect($document->end())->toBe('test body');
            expect($document->rewind())->toBe(1);

        });

    });

    describe("->valid()", function() {

        it("returns true only when the collection is valid", function() {

            $document = new Document();
            expect($document->valid())->toBe(false);

            $data = [
                'id'    => 1,
                'title' => 'test record',
                'body'  => 'test body'
            ];
            $document = new Document(['data' => $data]);
            expect($document->valid())->toBe(true);

        });

    });

    describe("->count()", function() {

        it("returns 0 on empty", function() {

            $document = new Document();
            expect($document)->toHaveLength(0);

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

            $document = new Document(['data' => $data]);
            expect($document)->toHaveLength(6);

        });

    });

    describe("->to('array')", function() {

        it("formats into an array", function() {

            $data = [
                'id'    => 1,
                'title' => 'test record'
            ];

            $document = new Document(['data' => $data]);
            expect($document->to('array'))->toBe($data);

        });

        it("formats nested relations", function() {

            $data = [
                'name'  => 'amiga_1200.jpg',
                'title' => 'Amiga 1200',
                'tags'  => [
                    ['name' => 'tag1']
                ]
            ];

            $image = new Document(['data' => $data]);
            expect($image->data())->toEqual($data);

        });

        it("exports generic relations", function() {

            $schema = new Schema();
            $schema->column('data', ['type' => 'object', 'default' => []]);
            $schema->column('data.*', ['type' => 'object', 'array' => true, 'default' => []]);
            $schema->column('data.*.count', ['type' => 'integer']);
            $schema->column('data.*.value', ['type' => 'integer']);

            $document = new Document(['schema' => $schema]);
            $data = [
                '2' => [['count' => '09', 'value' => 5]]
            ];
            $document->data = $data;
            expect($document->data())->toEqual([
                'data' => [
                    '2' => [
                        ['count' => 9, 'value' => 5]
                    ]
                ]
            ]);

        });

        context("with JSON formatter", function() {

            beforeEach(function() {

                $this->schema = new Schema();
                $this->schema->formatter('datasource', 'null', function($value, $column) {
                    return '';
                });
                $this->schema->formatter('datasource', 'json', function($value, $column) {
                    if (is_object($value)) {
                        $value = $value->data();
                    }
                    return json_encode($value);
                });
            });

            it("formats objects according JSON casting handlers", function() {

                $this->schema->column('timeSheet', [
                    'type' => 'object',
                    'default' => '{"1":null,"2":null,"3":null,"4":null,"5":null,"6":null,"7":null}',
                    'format' => 'json'
                ]);
                $this->schema->column('timeSheet.*', ['type' => 'integer']);

                $document = new Document(['schema' => $this->schema]);
                $document->set('timeSheet', '{"1":8,"2":8,"3":8,"4":8,"5":8,"6":8,"7":8}');
                expect($document->get('timeSheet')->data())->toEqual(['1' => 8, '2' => 8, '3' => 8, '4' => 8, '5' => 8, '6' => 8, '7' => 8]);
                expect($document->to('datasource'))->toEqual(['timeSheet' => '{"1":8,"2":8,"3":8,"4":8,"5":8,"6":8,"7":8}']);
                expect($document->data())->toEqual(['timeSheet' => ['1' => 8, '2' => 8, '3' => 8, '4' => 8, '5' => 8, '6' => 8, '7' => 8]]);

            });

            it("formats array according JSON casting handlers", function() {

                $this->schema->column('weekend', ['type' => 'integer', 'array' => true, 'format' => 'json', 'default' => '[6,7]']);

                $document = new Document(['schema' => $this->schema]);
                $document->set('weekend', '[1,2]');
                expect($document->weekend)->toBeAnInstanceOf(Collection::class);
                expect($document->weekend->data())->toBe([1, 2]);
                expect($document->to('datasource'))->toEqual(['weekend' => '[1,2]']);
                expect($document->data())->toEqual(['weekend' => [1, 2]]);

            });

            it("ignores the format option for `null` values", function() {

                $this->schema->column('data', ['type' => 'object', 'format' => 'json']);

                $document = new Document(['schema' => $this->schema]);
                $document->set('data', null);
                expect($document->data)->toBe(null);
                expect($document->to('datasource'))->toEqual(['data' => '']);

            });

            it("formats column default value according casting handlers", function() {

                $this->schema->column('timeSheet', [
                    'type' => 'object',
                    'default' => '{"1":null,"2":null,"3":null,"4":null,"5":null,"6":null,"7":null}',
                    'format' => 'json'
                ]);
                $this->schema->column('timeSheet.*', ['type' => 'integer']);

                $document = new Document(['schema' => $this->schema]);
                expect($document->get('timeSheet.1'))->toBe(null);

            });

        });

    });

    describe("->amend()", function() {

        it("amends a document", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            $document->title = 'modified';
            expect($document->modified('title'))->toBe(true);

            $document->amend();
            expect($document->modified('title'))->toBe(false);
            expect($document->get('title'))->toBe('modified');

        });

    });

    describe("->restore()", function() {

        it("restores a document to its original values", function() {

            $document = new Document([
                'data' => [
                    'title' => 'original'
                ]
            ]);

            expect($document->modified('title'))->toBe(false);

            $document->title = 'modified';
            expect($document->modified('title'))->toBe(true);

            $document->restore();
            expect($document->modified('title'))->toBe(false);
            expect($document->get('title'))->toBe('original');

        });
    });

});