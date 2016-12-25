<?php
namespace Chaos\ORM\Spec\Fixture\Model;

class ImageTag extends \Chaos\ORM\Model
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);

        $schema->belongsTo('image', Image::class, [
            'keys' => ['image_id' => 'id']
        ]);

        $schema->belongsTo('tag', Tag::class, [
            'keys' => ['tag_id' => 'id']
        ]);
    }
}
