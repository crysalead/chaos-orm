<?php
namespace chaos\spec\suite\database;

use set\Set;
use chaos\model\Model;
use chaos\source\database\Query;

use kahlan\plugin\Stub;
use chaos\spec\fixture\Fixtures;

describe("Schema", function() {

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
        $this->fixtures->populate('image');
        $this->fixtures->populate('image_tag');
        $this->fixtures->populate('tag');

        $this->gallery = $this->fixtures->get('gallery')->model();
        $this->image = $this->fixtures->get('image')->model();
    });

    afterEach(function() {
        $this->fixtures->drop();
    });

    it("embeds a hasMany relationship", function() {

        $model = $this->gallery;
        $galleries = $model::all(['order' => 'id']);
        $schema = $model::schema();
        $schema->embed($galleries, ['images']);

        foreach ($galleries as $gallery) {
            foreach ($gallery->images as $image) {
                expect($gallery->id)->toBe($image->gallery_id);
            }
        }

    });

    it("embeds a belongsTo relationship", function() {

        $image = $this->image;
        $images = $image::all(['order' => 'id']);
        $schema = $image::schema();
        $schema->embed($images, ['gallery']);

        foreach ($images as $image) {
            expect($image->gallery_id)->toBe($image->gallery->id);
        }

    });
});
