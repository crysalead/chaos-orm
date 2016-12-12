<?php
namespace Chaos\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\Model;
use Chaos\Schema;
use Chaos\Collection\Collection;

use Kahlan\Plugin\Double;

describe("Model", function() {

    beforeAll(function() {
        $model = $this->model = Double::classname(['extends' => Model::class]);
        $model::definition()->locked(false);
    });

    afterEach(function() {
        $model = $this->model;
        $model::reset();
        $model::definition()->locked(false);
    });

    describe("::conventions()", function() {

        it("gets/sets a conventions", function() {

            $conventions = Double::instance();
            $model = $this->model;
            $model::conventions($conventions);
            expect($model::conventions())->toBe($conventions);

        });

    });

    describe("::connection()", function() {

        it("gets/sets a connection", function() {

            $connection = Double::instance();
            allow($connection)->toReceive('formatters')->andRun(function() {
                return [];
            });
            $model = $this->model;
            $model::connection($connection);
            expect($model::connection())->toBe($connection);

        });

    });

    describe("::create()", function() {

        it("creates an entity", function() {

            $model = $this->model;
            $data = ['title' => 'Amiga 1200'];
            $entity = $model::create($data);

            expect($entity)->toBeAnInstanceOf($model);
            expect($entity->data())->toBe($data);
            expect($entity->exists())->toBe(false);

        });

        it("creates an existing entity", function() {

            $model = $this->model;
            $data = ['id' => '1', 'title' => 'Amiga 1200'];
            $entity = $model::create($data, ['exists' => true]);

            expect($entity)->toBeAnInstanceOf($model);
            expect($entity->data())->toBe($data);
            expect($entity->exists())->toBe(true);

        });

        it("creates a collection of entities", function() {

            $model = $this->model;
            $data = [
                ['title' => 'Amiga 1200'],
                ['title' => 'Las Vegas']
            ];
            $collection = $model::create($data, ['type' => 'set']);

            expect($collection)->toBeAnInstanceOf(Collection::class);
            expect($collection->data())->toBe($data);

            foreach ($collection as $entity) {
                expect($entity)->toBeAnInstanceOf($model);
                expect($entity->exists())->toBe(false);
            }

        });

        it("creates a collection of existing entities", function() {

            $model = $this->model;
            $data = [
                ['id' => '1', 'title' => 'Amiga 1200'],
                ['id' => '2', 'title' => 'Las Vegas']
            ];
            $collection = $model::create($data, ['type' => 'set', 'exists' => true]);

            expect($collection)->toBeAnInstanceOf(Collection::class);
            expect($collection->data())->toBe($data);

            foreach ($collection as $entity) {
                expect($entity)->toBeAnInstanceOf($model);
                expect($entity->exists())->toBe(true);
            }

        });

        it("creates an entity of a different class", function() {

            $model = $this->model;
            $subclass = Double::classname(['extends' => $model]);
            $entity = $model::create([], ['document' => $subclass]);

            expect($entity)->toBeAnInstanceOf($subclass);

        });

    });

    describe("::query()", function() {

        it("gets/sets the default query parameters", function() {

            $model = $this->model;
            $model::query(['field' => 'value']);
            expect($model::query())->toBe(['field' => 'value']);
            $model::query([]);

        });

    });

    describe("::validator()", function() {

        it("gets/sets a validator", function() {

            $validator = Double::instance();
            $model = $this->model;
            $model::validator($validator);
            expect($model::validator())->toBe($validator);

        });

    });

    describe("::finders()", function() {

        it("gets/sets a finders", function() {

            $finders = Double::instance();
            $model = $this->model;
            $model::finders($finders);
            expect($model::finders())->toBe($finders);

        });

    });

    describe("::find()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::definition();
            $this->query = $query = Double::instance(['methods' => ['method1', 'method2']]);
            allow($schema)->toReceive('query')->andRun(function() use ($query) {
                return $query;
            });
        });

        it("returns a query instance from the schema class", function() {

            $model = $this->model;
            expect($model::find())->toBe($this->query);

        });

        it("passes the finder instance to the query", function() {

            $model = $this->model;
            $schema = $model::definition();
            $finders = $model::finders();

            expect($schema)->toReceive('query')->with([
                'query'   => [],
                'finders' => $finders
            ]);
            $model::find();

        });

        it("merges default query parameters on find", function() {

            $model = $this->model;
            $schema = $model::definition();
            $finders = $model::finders();

            $model::query(['method1' => 'param1']);

            expect($schema)->toReceive('query')->with([
                'query'   => [
                    'method1' => 'param1',
                    'method2' => 'param2'
                ],
                'finders' => $finders
            ]);

            $model::find([
                'method2' => 'param2'
            ]);

        });

    });

    describe("::first()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::definition();
            $this->query = $query = Double::instance();
            allow($schema)->toReceive('query')->andRun(function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::find`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with(['query' => ['field' => 'value']]);
            expect($this->query)->toReceive('first')->with(['fetch' => 'options']);

            $model::first(['query' => ['field' => 'value']], ['fetch' => 'options']);

        });

    });

    describe("::load()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::definition();
            $this->query = $query = Double::instance();
            allow($schema)->toReceive('query')->andRun(function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::find`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with([
                'conditions' => ['id' => 1],
                'option' => 'value'
            ]);
            expect($this->query)->toReceive('first')->with(['fetch' => 'options']);

            $model::load(1, ['option' => 'value'], ['fetch' => 'options']);

        });

    });

    describe("::all()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::definition();
            $this->query = $query = Double::instance();
            allow($schema)->toReceive('query')->andRun(function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::all`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with(['query' => ['field' => 'value']]);
            expect($this->query)->toReceive('all')->with(['fetch' => 'options']);

            $model::all(['query' => ['field' => 'value']], ['fetch' => 'options']);

        });

    });

    describe("::definition()", function() {

        it("returns the definition", function() {

            $model = $this->model;
            $schema = $model::definition();
            expect($schema)->toBeAnInstanceOf('chaos\Schema');
            expect($schema)->toBe($model::definition());

        });

        it("gets/sets a finders", function() {

            $schema = Double::instance();
            $model = $this->model;
            $model::definition($schema);
            expect($model::definition())->toBe($schema);

        });

    });

});