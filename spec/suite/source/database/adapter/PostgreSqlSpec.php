<?php
namespace chaos\spec\suite;

use PDO;
use chaos\source\database\adapter\PostgreSql;
use chaos\source\Schema;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("PostgreSql", function() {

    before(function() {
        $this->adapter = box('chaos.spec')->get('source.database.postgresql');
        $this->fixtures = new Fixtures([
            'connection' => $this->adapter,
            'fixtures'   => [
                'gallery' => 'chaos\spec\fixture\schema\Gallery'
            ]
        ]);
    });

    afterEach(function() {
        $this->fixtures->drop();
    });

    describe("->sources()", function() {

        it("shows sources", function() {

            $this->fixtures->populate('gallery');
            $sources = $this->adapter->sources();

            expect($sources)->toBe([
                'gallery' => 'gallery'
            ]);

        });

    });

    describe("->describe()", function() {

        it("describe a source", function() {

            $this->fixtures->populate('gallery');

            $schema = $this->adapter->describe('gallery');

            expect($schema->field('id'))->toEqual([
                'type' => 'integer',
                'null' => false,
                'default' => null,
                'array' => false
            ]);

            expect($schema->field('name'))->toEqual([
                'type' => 'string',
                'length' => 255,
                'null' => true,
                'default' => null,
                'array' => false
            ]);

        });

    });

});

?>