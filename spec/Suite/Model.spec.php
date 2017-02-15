<?php
namespace Chaos\ORM\Spec\Suite;

use stdClass;
use DateTime;
use InvalidArgumentException;
use Chaos\ORM\Model;
use Chaos\ORM\Schema;
use Chaos\ORM\Collection\Collection;
use Chaos\ORM\Spec\Fixture\Model\Image;
use Chaos\ORM\Map;

use Kahlan\Plugin\Double;

describe("Model", function() {

    beforeAll(function() {
        $model = $this->model = Double::classname(['extends' => Model::class]);
        $model::definition()->lock(false);
    });

    afterEach(function() {
        $model = $this->model;
        $model::reset();
        $model::definition()->lock(false);
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
            $entity = $model::create([], ['class' => $subclass]);

            expect($entity)->toBeAnInstanceOf($subclass);

        });

        it("creates an entity using a custom collection class", function() {

            $model = $this->model;
            $MyCollection = Double::classname(['extends' => Collection::class]);
            $model::classes(['set' => $MyCollection]);

            $data = [
                ['id' => '1', 'title' => 'Amiga 1200'],
                ['id' => '2', 'title' => 'Las Vegas']
            ];
            $collection = $model::create($data, ['type' => 'set']);

            expect($collection)->toBeAnInstanceOf($MyCollection);

        });

        context("when unicity is enabled", function() {

            it("keeps a single reference of entities with the same ID", function() {

                $model = $this->model;
                $model::unicity(true);
                $data = ['id' => '1', 'title' => 'Amiga 1200'];
                $entity = $model::create($data, ['exists' => true]);

                expect($entity instanceof $model)->toBe(true);
                expect($entity->data())->toBe($data);
                expect($entity->exists())->toBe(true);

                $shard = $model::shard();
                expect($shard->has($entity->id()))->toBe(true);

                $entity2 = $model::create($data, ['exists' => true]);

                expect($entity)->toBe($entity2);

                expect($shard->count())->toBe(1);

                $model::reset();

            });

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
            expect($schema)->toBeAnInstanceOf('Chaos\ORM\Schema');
            expect($schema)->toBe($model::definition());

        });

        it("gets/sets a finders", function() {

            $schema = Double::instance();
            $model = $this->model;
            $model::definition($schema);
            expect($model::definition())->toBe($schema);

        });

    });

    describe(".unicity()", function() {

        it("gets/sets unicity", function() {

            $model = $this->model;
            $model::unicity(true);
            expect($model::unicity())->toBe(true);

            $model = $this->model;
            $model::reset();
            expect($model::unicity())->toBe(false);

        });

    });

    describe(".shard()", function() {

        beforeEach(function() {
            $model = $this->model;
            $model::unicity(true);
        });

        afterEach(function() {
            $model = $this->model;
            $model::reset();
        });

        it("gets/sets a shard", function() {

            $model = $this->model;
            $shard = new Map();
            expect($model::shard($shard))->toBe(null);
            expect($model::shard())->toBe($shard);

        });

        it("gets the default shard", function() {

            $model = $this->model;
            $shard = $model::shard();
            expect($model::shard())->toBeAnInstanceOf(Map::class);
            expect($model::shard())->toBe($shard);

            expect(Image::shard())->not->toBe($shard);

        });

        it("deletes a shard", function() {

            $model = $this->model;
            $shard = $model::shard();

            $model::shard(false);
            expect($model::shard())->not->toBe($shard);

        });

    });

});