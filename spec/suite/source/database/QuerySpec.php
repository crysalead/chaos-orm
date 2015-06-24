<?php
namespace chaos\spec\suite\database;

use set\Set;
use chaos\model\Model;
use chaos\source\database\Query;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("Query", function() {

    beforeEach(function() {

        $this->connection = box('chaos.spec')->get('source.database.mysql');
        $this->fixtures = new Fixtures([
            'connection' => $this->connection,
            'fixtures'   => [
                'gallery'   => 'chaos\spec\fixture\schema\Gallery',
                'image'     => 'chaos\spec\fixture\schema\Image',
                'image_tag' => 'chaos\spec\fixture\schema\ImageTag',
                'tag'       => 'chaos\spec\fixture\schema\Tag'
            ]
        ]);

        $this->fixtures->populate('gallery');
        $gallery = $this->fixtures->get('gallery')->model();

        $this->query = new Query([
            'model'      => $gallery,
            'connection' => $this->connection
        ]);

        $this->query->order(['id']);

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

        it("finds all records using array hydration", function() {

            $result = $this->query->get(['return' => 'array']);
            expect($result)->toBe([
                ['id' => '1', 'name' => 'Foo Gallery'],
                ['id' => '2', 'name' => 'Bar Gallery']
            ]);

        });

        it("finds all records using object hydration", function() {

            $result = $this->query->get(['return' => 'object']);
            expect($result)->toEqual([
                json_decode(json_encode(['id' => '1', 'name' => 'Foo Gallery']), false),
                json_decode(json_encode(['id' => '2', 'name' => 'Bar Gallery']), false),
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

        it("delegates the call up to the model if a method exists", function() {

            $gallery = Stub::classname([
                'extends' => Model::class,
                'methods' => ['::bar']
            ]);

            Stub::on($gallery)->method('::bar', function($query) {
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

            expect($gallery)->toReceive('::bar')->with($query);

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

    xdescribe("->with()", function() {

        beforeEach(function() {
            $this->fixtures->populate('image');
            $this->fixtures->populate('image_tag');
            $this->fixtures->populate('tag');
        });

        it("finds all records with their relation", function() {

            $result = $this->query->with([
                'images' => ['super' => 1],
                'images.tags' => ['toto' => 2]
            ])->all()->data();
            expect($this->query->with())->toBe([]);

        });

    });

});
