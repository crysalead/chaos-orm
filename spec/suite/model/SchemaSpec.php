<?php
namespace chaos\spec\suite\model;

use stdClass;
use DateTime;
use InvalidArgumentException;
use chaos\SourceException;
use chaos\model\Model;
use chaos\model\Schema;

use kahlan\plugin\Stub;

describe("Schema", function() {

    beforeEach(function() {

        $this->schema = new Schema();

        $this->schema->set('id', ['type' => 'serial']);
        $this->schema->set('gallery_id', ['type' => 'integer']);
        $this->schema->set('name', ['type' => 'string', 'default' => 'Enter The Name Here']);
        $this->schema->set('title', ['type' => 'string', 'default' => 'Enter The Title Here', 'length' => 50]);

        $this->schema->bind('gallery', [
            'relation' => 'belongsTo',
            'to'       => 'chaos\spec\fixture\model\Gallery',
            'keys'     => ['gallery_id' => 'id']
        ]);

        $this->schema->bind('images_tags', [
            'relation' => 'hasMany',
            'to'       => 'chaos\spec\fixture\model\ImageTag',
            'keys'     => ['id' => 'image_id']
        ]);

        $this->schema->bind('tags', [
            'relation' => 'hasManyThrough',
            'through'  => 'images_tags',
            'using'    => 'tag'
        ]);

    });

    describe("->set()", function() {

        beforeEach(function() {
            $this->schema = new Schema();
        });

        it("sets a field with default values", function() {

            $this->schema->set('name');
            expect($this->schema->field('name'))->toBe([
                'type' => 'string',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field with a specific type", function() {

            $this->schema->set('age', ['type' => 'integer']);
            expect($this->schema->field('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field with a specific type using the array syntax", function() {

            $this->schema->set('age', ['integer']);
            expect($this->schema->field('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field with a specific type using the string syntax", function() {

            $this->schema->set('age', 'integer');
            expect($this->schema->field('age'))->toBe([
                'type' => 'integer',
                'array' => false,
                'null' => true
            ]);

        });

        it("sets a field as an array", function() {

            $this->schema->set('ids', ['type' => 'integer', 'array' => true]);
            expect($this->schema->field('ids'))->toBe([
                'type' => 'integer',
                'array' => true,
                'null' => true
            ]);

        });

        it("sets a field with custom options", function() {

            $this->schema->set('name', ['type' => 'integer', 'length' => 11, 'use' => 'bigint']);
            expect($this->schema->field('name'))->toBe([
                'type'   => 'integer',
                'length' => 11,
                'use'    => 'bigint',
                'array'  => false,
                'null'   => true
            ]);

        });

    });

    describe("->fields()", function() {

        it("returns all fields", function() {

            expect($this->schema->fields())->toBe([
                'id' => [
                    'type'  => 'serial',
                    'array' => false,
                    'null'  => true
                ],
                'gallery_id' => [
                    'type'  => 'integer',
                    'array' => false,
                    'null'  => true
                ],
                'name' => [
                    'type'    => 'string',
                    'default' => 'Enter The Name Here',
                    'array'   => false,
                    'null'    => true
                ],
                'title' => [
                    'type'    => 'string',
                    'default' => 'Enter The Title Here',
                    'length'  => 50,
                    'array'   => false,
                    'null'    => true
                ]
            ]);

        });

        it("returns an attribute only", function() {

            expect($this->schema->fields('default'))->toBe([
                'name'  => 'Enter The Name Here',
                'title' => 'Enter The Title Here'
            ]);

            expect($this->schema->fields('type'))->toBe([
                'id'         => 'serial',
                'gallery_id' => 'integer',
                'name'       => 'string',
                'title'      => 'string'
            ]);

        });

    });

});
/*
    public function testShortHandTypeDefinitions() {
        $schema = new Schema(array('fields' => array(
            'id' => 'int',
            'name' => 'string',
            'active' => array('type' => 'boolean', 'default' => true)
        )));

        $this->assertEqual('int', $schema->type('id'));
        $this->assertEqual('string', $schema->type('name'));
        $this->assertEqual('boolean', $schema->type('active'));
        $this->assertEqual(array('type' => 'int'), $schema->fields('id'));
        $this->assertEqual(array('id', 'name', 'active'), $schema->names());

        $expected = array(
            'id' => array('type' => 'int'),
            'name' => array('type' => 'string'),
            'active' => array('type' => 'boolean', 'default' => true)
        );
        $this->assertEqual($expected, $schema->fields());
    }
}
*/