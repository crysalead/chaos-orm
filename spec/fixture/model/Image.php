<?php
namespace chaos\spec\fixture\model;

class Image extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    protected static function _meta()
    {
        return ['source' => 'image'];
    }

    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('gallery_id', ['type' => 'integer']);
        $schema->set('name', ['type' => 'string']);
        $schema->set('title', ['type' => 'string', 'length' => 50]);

        $schema->bind('gallery', [
            'relation'    => 'belongsTo',
            'to'          => 'chaos\spec\fixture\model\Gallery',
            'key'         => 'gallery_id'
        ]);

        $schema->bind('images_tags', [
            'relation'    => 'hasMany',
            'to'          => 'chaos\spec\fixture\model\ImageTag',
            'key'         => 'image_id'
        ]);

        $schema->bind('tags', [
            'relation'    => 'hasManyThrough',
            'to'          => 'chaos\spec\fixture\model\Tag',
            'key'         => 'tag_id',
            'through'     => 'images_tags'
        ]);
    }
}
