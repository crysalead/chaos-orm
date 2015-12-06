<?php
namespace Chaos\Spec\Fixture\Model;

class ImageTag extends \Chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('image_id', ['type' => 'integer']);
        $schema->set('tag_id', ['type' => 'integer']);

        $schema->belongsTo('image', Image::class, [
            'keys' => ['image_id' => 'id']
        ]);

        $schema->belongsTo('tag', Tag::class, [
            'keys' => ['tag_id' => 'id']
        ]);
    }
}
