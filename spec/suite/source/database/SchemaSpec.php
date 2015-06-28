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
                'gallery'        => 'chaos\spec\fixture\schema\Gallery',
                'gallery_detail' => 'chaos\spec\fixture\schema\GalleryDetail',
                'image'          => 'chaos\spec\fixture\schema\Image',
                'image_tag'      => 'chaos\spec\fixture\schema\ImageTag',
                'tag'            => 'chaos\spec\fixture\schema\Tag'
            ]
        ]);

        $this->fixtures->populate('gallery');
        $this->fixtures->populate('gallery_detail');
        $this->fixtures->populate('image');
        $this->fixtures->populate('image_tag');
        $this->fixtures->populate('tag');

        $this->gallery = $this->fixtures->get('gallery')->model();
        $this->galleryDetail = $this->fixtures->get('gallery_detail')->model();
        $this->image = $this->fixtures->get('image')->model();
        $this->image_tag = $this->fixtures->get('image_tag')->model();
        $this->tag = $this->fixtures->get('tag')->model();
    });

    afterEach(function() {
        $this->fixtures->drop();
    });

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

});
