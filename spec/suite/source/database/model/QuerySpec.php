<?php
namespace chaos\spec\suite;

use chaos\source\database\model\Model;
use chaos\source\database\model\Query;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("Query", function() {

    beforeEach(function() {

        $this->connection = box('chaos.spec')->get('source.database.mysql');
        $this->fixtures = new Fixtures([
            'classes'    => [
                'model' => 'chaos\source\database\model\Model'
            ],
            'connection' => $this->connection,
            'fixtures'   => [
                'gallery' => 'chaos\spec\fixture\sample\Gallery',
                'image'   => 'chaos\spec\fixture\sample\Image'
            ]
        ]);

        $this->fixtures->populate('gallery');
        $gallery = $this->fixtures->get('gallery')->model();

        $this->query = new Query([
            'model'      => $gallery,
            'connection' => $this->connection
        ]);

        $this->query->order('id');

    });

    afterEach(function() {
        $this->fixtures->drop();
    });

    describe("->connection()", function() {

        it("returns the connection", function() {

            expect($this->query->connection())->toBe($this->connection);

        });

    });

    describe("->all()", function() {

        it("finds all records", function() {

            $result = $this->query->all()->data();
            expect($result)->toBe([
                ['id' => '1', 'name' => 'Foo Gallery'],
                ['id' => '2', 'name' => 'Bar Gallery']
            ]);

        });

    });

    describe("->get()", function() {

        it("finds all records", function() {

            $result = $this->query->get()->data();
            expect($result)->toBe([
                ['id' => '1', 'name' => 'Foo Gallery'],
                ['id' => '2', 'name' => 'Bar Gallery']
            ]);

        });

    });

    describe("->first()", function() {

        it("finds the first record", function() {

            $result = $this->query->first()->data();
            expect($result)->toBe(['id' => '1', 'name' => 'Foo Gallery']);

        });

    });

    describe("->getIterator()", function() {

        it("implements `IteratorAggregate`", function() {

            $this->query->where(['name' => 'Foo Gallery']);

            foreach ($this->query as $record) {
                expect($record->data())->toBe(['id' => '1', 'name' => 'Foo Gallery']);
            }

        });

    });

    describe("->__call()", function() {

        it("delegates the call up to the model if a corresponding scope method exists", function() {

            $gallery = Stub::classname([
                'extends' => Model::class,
                'methods' => ['::scopeBar']
            ]);

            Stub::on($gallery)->method('::scopeBar', function($query) {
                return $query->where(['name' => 'Bar Gallery']);
            });

            $gallery::config([
                'connection' => $this->connection,
                'schema'     => $this->fixtures->get('gallery')->schema()
            ]);

            $query = new Query([
                'model'      => $gallery,
                'connection' => $this->connection
            ]);

            $result = $query->bar()->first()->data();
            expect($result)->toBe(['id' => '2', 'name' => 'Bar Gallery']);

        });

    });

    describe("->count()", function() {

        it("finds all records", function() {

            $count = $this->query->count();
            expect($count)->toBe(2);

        });

    });

    describe("->with()", function() {

        beforeEach(function() {
            $this->fixtures->populate('image');
        });

        it("finds all records with their relation", function() {

            $result = $this->query->with(['Image'])->all()->data();
            expect($result)->toBe([]);

        });

    });

});
