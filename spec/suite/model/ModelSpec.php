<?php
namespace chaos\spec\suite\model;

use stdClass;
use DateTime;
use InvalidArgumentException;
use chaos\SourceException;
use chaos\model\Model;
use chaos\model\Schema;

use kahlan\plugin\Stub;

describe("Model", function() {

    before(function() {
        $this->model = Stub::classname(['extends' => Model::class]);
    });

    afterEach(function() {
        $model = $this->model;
        $model::config(); // (acts like a reset)
    });

    describe("::conventions()", function() {

        it("gets/sets a conventions", function() {

            $conventions = Stub::create();
            $model = $this->model;
            $model::conventions($conventions);
            expect($model::conventions())->toBe($conventions);

        });

    });

    describe("::connection()", function() {

        it("gets/sets a connection", function() {

            $connection = Stub::create();
            $model = $this->model;
            $model::connection($connection);
            expect($model::connection())->toBe($connection);

        });

    });

    describe("::query()", function() {

        it("gets/sets the default query parameters", function() {

            $connection = Stub::create();
            $model = $this->model;
            $model::query(['query' => 'options']);
            expect($model::query())->toBe(['query' => 'options']);

        });

    });

    describe("::find()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::schema();
            $this->query = $query = Stub::create(['methods' => ['method1', 'method2']]);
            Stub::on($schema)->method('query', function() use ($query) {
                return $query;
            });
        });

        it("returns a query instance from the schema class", function() {

            $model = $this->model;
            expect($model::find())->toBe($this->query);

        });

        it("delegates passed options", function() {

            $model = $this->model;

            expect($this->query)->toReceive('method1')->with('param1');
            expect($this->query)->toReceive('method2')->with('param2');

            $model::find([
                'method1' => 'param1',
                'method2' => 'param2'
            ]);

        });

        it("merges default query parameters on find", function() {

            $model = $this->model;

            $model::query(['method1' => 'param1']);

            expect($this->query)->toReceive('method1')->with('param1');
            expect($this->query)->toReceive('method2')->with('param2');

            $model::find([
                'method2' => 'param2'
            ]);

        });

    });

    describe("::first()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::schema();
            $this->query = $query = Stub::create();
            Stub::on($schema)->method('query', function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::find`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with(['query' => 'options']);
            expect($this->query)->toReceive('first')->with(['fetch' => 'options']);

            $model::first(['query' => 'options'], ['fetch' => 'options']);

        });

    });

    describe("::id()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::schema();
            $this->query = $query = Stub::create();
            Stub::on($schema)->method('query', function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::find`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with([
                'query' => 'options',
                'conditions' => ['id' => 1]
            ]);
            expect($this->query)->toReceive('first')->with(['fetch' => 'options']);

            $model::id(1, ['query' => 'options'], ['fetch' => 'options']);

        });

    });

    describe("::all()", function() {

        beforeEach(function() {
            $model = $this->model;
            $schema = $model::schema();
            $this->query = $query = Stub::create();
            Stub::on($schema)->method('query', function() use ($query) {
                return $query;
            });
        });

        it("delegates to `::all`", function() {

            $model = $this->model;

            expect($model)->toReceive('::find')->with(['query' => 'options']);
            expect($this->query)->toReceive('all')->with(['fetch' => 'options']);

            $model::all(['query' => 'options'], ['fetch' => 'options']);

        });

    });

    describe("::schema()", function() {

        it("returns the model", function() {

            $model = $this->model;
            $schema = $model::schema();
            expect($schema)->toBeAnInstanceOf('chaos\model\Schema');
            expect($schema)->toBe($model::schema());

        });

    });

    describe("::relations()", function() {

        it("delegates calls to schema", function() {

            $model = $this->model;

            expect($model::schema())->toReceive('relations')->with('hasMany');

            $model::relations('hasMany');

        });

    });

    describe("::relation()", function() {

        it("delegates calls to schema", function() {

            $model = $this->model;

            $schema = $model::schema();
            $schema->bind('abc', [
                'relation' => 'hasOne',
                'to'       => 'TargetModel',
            ]);

            expect($model::schema())->toReceive('relation')->with('abc');

            $model::relation('abc');

        });

    });

});