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

        describe("->__call()", function() {

            it("delegates the call up to the model if a method exists", function() {

                $this->fixtures->populate('gallery');

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
                expect($result)->toEqual(['id' => '2', 'name' => 'Bar Gallery']);

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

        describe("->save()", function() {

            it("saves a hasMany relationship", function() {

                $data = [
                    'name' => 'Foo Gallery',
                    'images' => [
                        ['name' => 'amiga_1200.jpg', 'title' => 'Amiga 1200'],
                        ['name' => 'srinivasa_ramanujan.jpg', 'title' => 'Srinivasa Ramanujan'],
                        ['name' => 'las_vegas.jpg', 'title' => 'Las Vegas'],
                    ]
                ];

                $model = $this->gallery;
                $gallery = $model::create($data);
                expect($gallery->save(['with' => true]))->toBe(true);

                expect($gallery->primaryKey())->not->toBe(null);
                foreach ($gallery->images as $image) {
                    expect($image->gallery_id)->toBe($gallery->primaryKey());
                }

                $result = $model::id($gallery->primaryKey(),  ['with' => ['images']]);
                expect($gallery->data())->toEqual($result->data());

            });

            it("saves a belongsTo relationship", function() {

                $data = [
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200',
                    'gallery' => [
                        'name' => 'Foo Gallery'
                    ]
                ];

                $model = $this->image;
                $image = $model::create($data);
                expect($image->save(['with' => true]))->toBe(true);

                expect($image->primaryKey())->not->toBe(null);
                expect($image->gallery_id)->toBe($image->gallery->primaryKey());

                $result = $model::id($image->primaryKey(),  ['with' => ['gallery']]);
                expect($image->data())->toEqual($result->data());

            });

            it("saves a hasOne relationship", function() {

                $data = [
                    'name' => 'Foo Gallery',
                    'detail' => [
                        'description' => 'Foo Gallery Description'
                    ]
                ];

                $model = $this->gallery;
                $gallery = $model::create($data);

                expect($gallery->save(['with' => true]))->toBe(true);

                expect($gallery->primaryKey())->not->toBe(null);
                expect($gallery->detail->gallery_id)->toBe($gallery->primaryKey());

                $result = $model::id($gallery->primaryKey(),  ['with' => ['detail']]);
                expect($gallery->data())->toEqual($result->data());

            });

            it("saves a hasManyTrough relationship", function() {

                $data = [
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200',
                    'gallery' => [
                        'name' => 'Foo Gallery'
                    ],
                    'tags' => [
                        ['name' => 'tag1'],
                        ['name' => 'tag2'],
                        ['name' => 'tag3']
                    ]
                ];

                $model = $this->image;
                $image = $model::create($data);
                expect($image->save(['with' => true]))->toBe(true);

                expect($image->primaryKey())->not->toBe(null);
                expect($image->images_tags)->toHaveLength(3);
                expect($image->tags)->toHaveLength(3);

                foreach ($image->images_tags as $index => $image_tag) {
                    expect($image_tag->tag_id)->toBe($image_tag->tag->primaryKey());
                    expect($image_tag->image_id)->toBe($image->primaryKey());
                    expect($image_tag->tag)->toBe($image->tags[$index]);
                }

                $result = $model::id($image->primaryKey(),  ['with' => ['gallery', 'tags']]);
                expect($image->data())->toEqual($result->data());

            });

            it("saves a nested entities", function() {

                $data = [
                    'name' => 'Foo Gallery',
                    'images' => [
                        [
                            'name' => 'amiga_1200.jpg',
                            'title' => 'Amiga 1200',
                            'tags' => [
                                ['name' => 'tag1'],
                                ['name' => 'tag2'],
                                ['name' => 'tag3']
                            ]
                        ]
                    ]
                ];

                $model = $this->gallery;
                $gallery = $model::create($data);
                expect($gallery->save(['with' => true]))->toBe(true);

                expect($gallery->primaryKey())->not->toBe(null);
                expect($gallery->images)->toHaveLength(1);

                foreach ($gallery->images as $image) {
                    expect($image->gallery_id)->toBe($gallery->primaryKey());
                    expect($image->images_tags)->toHaveLength(3);
                    expect($image->tags)->toHaveLength(3);

                    foreach ($image->images_tags as $index => $image_tag) {
                        expect($image_tag->tag_id)->toBe($image_tag->tag->primaryKey());
                        expect($image_tag->image_id)->toBe($image->primaryKey());
                        expect($image_tag->tag)->toBe($image->tags[$index]);
                    }
                }

                $result = $model::id($gallery->primaryKey(),  ['with' => ['images.tags']]);
                expect($gallery->data())->toEqual($result->data());

            });

        });

    });

}
