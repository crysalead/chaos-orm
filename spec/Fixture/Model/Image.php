<?php
namespace Chaos\Spec\Fixture\Model;

class Image extends \Chaos\Model
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('gallery_id', ['type' => 'integer']);
        $schema->column('name', ['type' => 'string']);
        $schema->column('title', ['type' => 'string', 'length' => 50]);
        $schema->column('score', ['type' => 'float' ]);

        $schema->belongsTo('gallery', Gallery::class, [
            'keys' => ['gallery_id' => 'id']
        ]);

        $schema->hasMany('images_tags', ImageTag::class, [
            'keys' => ['id' => 'image_id']
        ]);

        $schema->hasManyThrough('tags', 'images_tags', 'tag');
    }
}
