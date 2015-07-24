<?php
namespace chaos\spec\suite\database;

use set\Set;
use chaos\SourceException;
use chaos\model\Model;
use chaos\source\database\Query;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

$box = box('chaos.spec');

$connections = [
    "MySQL" => $box->has('source.database.mysql') ? $box->get('source.database.mysql') : null,
    "PgSql" => $box->has('source.database.postgresql') ? $box->get('source.database.postgresql') : null
];

foreach ($connections as $db => $connection) {

    describe("Query[{$db}]", function() use ($connection) {

        beforeEach(function() use ($connection) {

            skipIf(!$connection);

            $this->connection = $connection;
            $this->fixtures = new Fixtures([
                'connection' => $connection,
                'fixtures'   => [
                    'gallery'        => 'chaos\spec\fixture\schema\Gallery',
                    'gallery_detail' => 'chaos\spec\fixture\schema\GalleryDetail',
                    'image'          => 'chaos\spec\fixture\schema\Image',
                    'image_tag'      => 'chaos\spec\fixture\schema\ImageTag',
                    'tag'            => 'chaos\spec\fixture\schema\Tag'
                ]
            ]);

            $this->fixtures->populate('gallery', ['create']);
            $this->fixtures->populate('gallery_detail', ['create']);
            $this->fixtures->populate('image', ['create']);
            $this->fixtures->populate('image_tag', ['create']);
            $this->fixtures->populate('tag', ['create']);

            $this->gallery = $this->fixtures->get('gallery')->model();
            $this->galleryDetail = $this->fixtures->get('gallery_detail')->model();
            $this->image = $this->fixtures->get('image')->model();
            $this->image_tag = $this->fixtures->get('image_tag')->model();
            $this->tag = $this->fixtures->get('tag')->model();

            $this->query = new Query([
                'model'      => $this->gallery,
                'connection' => $this->connection
            ]);

            $this->query->order(['id']);

        });

        afterEach(function() {
            $this->fixtures->drop();
            $this->fixtures->reset();
        });

        describe("->connection()", function() {

            it("returns the connection", function() {

                expect($this->query->connection())->toBe($this->connection);

            });

            it("throws an error if no connection is available", function() {

                $closure = function() {
                    $this->query = new Query(['model' => $this->gallery]);
                    $this->query->connection();
                };


                expect($closure)->toThrow(new SourceException("Error, missing connection for this query."));

            });

        });

        describe("->statement()", function() {

            it("returns the select statement", function() {

                $statement = $this->query->statement();
                $class = get_class($statement);
                $pos = strrpos($class, '\\');
                $basename = substr($class, $pos !== false ? $pos + 1 : 0);
                expect($basename)->toBe('Select');

            });

        });

        describe("->all()", function() {

            it("finds all records", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->all()->data();
                expect($result)->toEqual([
                    ['id' => '1', 'name' => 'Foo Gallery'],
                    ['id' => '2', 'name' => 'Bar Gallery']
                ]);

            });

            it("filering out some fields", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->fields('name')->all()->data();
                expect($result)->toEqual([
                    ['name' => 'Foo Gallery'],
                    ['name' => 'Bar Gallery']
                ]);

            });

        });

        describe("->get()", function() {

            it("finds all records", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->get()->data();
                expect($result)->toEqual([
                    ['id' => '1', 'name' => 'Foo Gallery'],
                    ['id' => '2', 'name' => 'Bar Gallery']
                ]);

            });

            it("finds all records using array hydration", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->get(['return' => 'array']);
                expect($result)->toEqual([
                    ['id' => '1', 'name' => 'Foo Gallery'],
                    ['id' => '2', 'name' => 'Bar Gallery']
                ]);

            });

            it("finds all records using object hydration", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->get(['return' => 'object']);
                expect($result)->toEqual([
                    json_decode(json_encode(['id' => '1', 'name' => 'Foo Gallery']), false),
                    json_decode(json_encode(['id' => '2', 'name' => 'Bar Gallery']), false),
                ]);

            });

            it("throws an error if the return mode is not supported", function() {

                $this->fixtures->populate('gallery');

                $closure = function() {
                    $result = $this->query->get(['return' => 'unsupported']);
                };

                expect($closure)->toThrow(new SourceException("Invalid `'unsupported'` mode as `'return'` value"));

            });

        });

        describe("->first()", function() {

            it("finds the first record", function() {

                $this->fixtures->populate('gallery');

                $result = $this->query->first()->data();
                expect($result)->toEqual(['id' => '1', 'name' => 'Foo Gallery']);

            });

        });

        describe("->getIterator()", function() {

            it("implements `IteratorAggregate`", function() {

                $this->fixtures->populate('gallery');

                $this->query->where(['name' => 'Foo Gallery']);

                foreach ($this->query as $record) {
                    expect($record->data())->toEqual(['id' => '1', 'name' => 'Foo Gallery']);
                }

            });

        });

        describe("->count()", function() {

            it("finds all records", function() {

                $this->fixtures->populate('gallery');

                $query = new Query([
                    'model'      => $this->gallery,
                    'connection' => $this->connection
                ]);
                $count = $query->count();
                expect($count)->toBe(2);

            });

        });

        describe("->alias()", function() {

            it("returns the alias value of table name by default", function() {

                expect($this->query->alias())->toBe('gallery');

            });

        });

    });

}
