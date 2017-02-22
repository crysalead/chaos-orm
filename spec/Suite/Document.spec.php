<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\ORM\ORMException;
use Chaos\ORM\Document;
use Chaos\ORM\Schema;

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
            expect($document->get('a'))->toBe(null);

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

    describe("->persisted()", function() {

        it("returns persisted data", function() {

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

            expect($document->persisted('title'))->toBe('Hello');
            expect($document->persisted('body'))->toBe('World');

            expect($document->title)->toBe('Good Bye');
            expect($document->body)->toBe('Folks');

            expect($document->modified('title'))->toBe(true);
            expect($document->modified('body'))->toBe(true);

        });

        it("returns all persisted data with no parameter", function() {

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

            expect($document->persisted())->toBe([
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

        it("returns `false` when an unexisting field is checked", function() {

            $document = new Document();
            expect($document->modified('unexisting'))->toBe(false);

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

        it("exports into an array", function() {

            $data = [
                'id'    => 1,
                'title' => 'test record'
            ];

            $document = new Document(['data' => $data]);
            expect($document->to('array'))->toBe($data);

        });

        it("exports nested relations", function() {

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

    });

    describe(".amend()", function() {

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

        });

    });

});