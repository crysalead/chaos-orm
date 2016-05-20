<?php
namespace Chaos\Spec\Fixture\Model;

class Image extends \Chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('gallery_id', ['type' => 'integer']);
        $schema->set('name', ['type' => 'string']);
        $schema->set('title', ['type' => 'string', 'length' => 50]);
        $schema->set('score', ['type' => 'float' ]);

        $schema->belongsTo('gallery', Gallery::class, [
            'keys' => ['gallery_id' => 'id']
        ]);

        $schema->hasMany('images_tags', ImageTag::class, [
            'keys' => ['id' => 'image_id']
        ]);

        $schema->hasManyThrough('tags', 'images_tags', 'tag');
    }
}
