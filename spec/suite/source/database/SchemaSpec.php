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

    describe("Schema[{$db}]", function() use ($connection) {

        beforeEach(function() use ($connection) {

            skipIf(!$connection);

            $this->connection = $connection;
            $this->fixtures = new Fixtures([
                'connection' => $this->connection,
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

        });

        afterEach(function() {
            $this->fixtures->drop();
            $this->fixtures->reset();
        });

        context("with all data populated", function() {

            beforeEach(function() {

                $this->fixtures->populate('gallery', ['records']);
                $this->fixtures->populate('gallery_detail', ['records']);
                $this->fixtures->populate('image', ['records']);
                $this->fixtures->populate('image_tag', ['records']);
                $this->fixtures->populate('tag', ['records']);

            });

            describe("->embed()", function() {

                it("embeds a hasMany relationship", function() {

                    $model = $this->gallery;
                    $schema = $model::schema();
                    $galleries = $model::all(['order' => 'id']);
                    $schema->embed($galleries, ['images']);

                    foreach ($galleries as $gallery) {
                        foreach ($gallery->images as $image) {
                            expect($gallery->id)->toBe($image->gallery_id);
                        }
                    }

                });

                it("embeds a belongsTo relationship", function() {

                    $model = $this->image;
                    $schema = $model::schema();
                    $images = $model::all(['order' => 'id']);
                    $schema->embed($images, ['gallery']);

                    foreach ($images as $image) {
                        expect($image->gallery_id)->toBe($image->gallery->id);
                    }

                });

                it("embeds a hasOne relationship", function() {

                    $model = $this->gallery;
                    $schema = $model::schema();
                    $galleries = $model::all(['order' => 'id']);
                    $schema->embed($galleries, ['detail', 'images']);

                    foreach ($galleries as $gallery) {
                        expect($gallery->id)->toBe($gallery->detail->gallery_id);
                    }

                });

                it("embeds a hasManyTrough relationship", function() {

                    $model = $this->image;
                    $schema = $model::schema();
                    $images = $model::all(['order' => 'id']);
                    $schema->embed($images, ['tags']);

                    foreach ($images as $image) {
                        foreach ($image->images_tags as $index => $image_tag) {
                            expect($image_tag->tag)->toBe($image->tags[$index]);
                        }
                    }
                });

                it("embeds nested hasManyTrough relationship", function() {

                    $model = $this->image;
                    $schema = $model::schema();
                    $images = $model::all(['order' => 'id']);
                    $schema->embed($images, ['tags.images']);

                    foreach ($images as $image) {
                        foreach ($image->images_tags as $index => $image_tag) {
                            expect($image_tag->tag)->toBe($image->tags[$index]);

                            foreach ($image_tag->tag->images_tags as $index2 => $image_tag2) {
                                expect($image_tag2->image)->toBe($image_tag->tag->images[$index2]);
                            }
                        }
                    }
                });

            });

        });

        describe("->save()", function() {

            it("saves an entity", function() {

                $data = [
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200'
                ];

                $model = $this->image;
                $image = $model::create($data);
                expect($image->save())->toBe(true);
                expect($image->exists())->toBe(true);
                expect($image->primaryKey())->not->toBe(null);

            });

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
                expect($gallery->save())->toBe(true);

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
                expect($image->save())->toBe(true);

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

                expect($gallery->save())->toBe(true);

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
                expect($image->save())->toBe(true);

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
                expect($gallery->save(['with' => 'images.tags']))->toBe(true);

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

            it("validates by default", function() {

                $model = $this->image;
                $image = $model::create([]);
                $model::validator()->rule('name', 'not:empty');

                expect($image->save())->toBe(false);
                expect($image->exists())->toBe(false);

            });

            it("validates direct relationships by default", function() {

                $gallery = $this->gallery;
                $gallery::validator()->rule('name', 'not:empty');


                $model = $this->image;
                $image = $model::create([
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200',
                    'gallery' => []
                ]);
                expect($image->save())->toBe(false);
                expect($image->exists())->toBe(false);

            });

            it("throws an exception when trying to update an entity with no ID data", function() {

                $closure = function() {
                    $model = $this->gallery;
                    $gallery = $model::create([], ['exists' => true]);
                    $gallery->name = 'Foo Gallery';
                    $gallery->save();
                };

                expect($closure)->toThrow(new SourceException("Can't update an entity missing ID data."));

            });

            it("throws an exception when trying to update an entity with no ID data and exists is `null`", function() {

                $closure = function() {
                    $model = $this->gallery;
                    $gallery = $model::create([], ['exists' => null, 'autoreload' => false]);
                    $gallery->name = 'Foo Gallery';
                    $gallery->save();
                };

                expect($closure)->toThrow(new SourceException("Can't update an entity missing ID data."));

            });

        });

        describe("->persist()", function() {

            it("saves an entity", function() {

                $data = [
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200'
                ];

                $model = $this->image;
                $image = $model::create($data);

                expect($image)->toReceive('save')->with([
                    'custom' => 'option',
                    'with' => false
                ]);

                expect($image->persist(['custom' => 'option']))->toBe(true);
                expect($image->exists())->toBe(true);
                expect($image->primaryKey())->not->toBe(null);

            });

        });

        describe("->delete()", function() {

            it("deletes an entity", function() {

                $data = [
                    'name' => 'amiga_1200.jpg',
                    'title' => 'Amiga 1200'
                ];

                $model = $this->image;
                $image = $model::create($data);

                expect($image->save())->toBe(true);
                expect($image->exists())->toBe(true);

                expect($image->delete())->toBe(true);
                expect($image->exists())->toBe(false);

            });

        });

    });

};
