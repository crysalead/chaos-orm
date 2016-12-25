<?php
namespace Chaos\ORM\Spec\Fixture\Model;

class GalleryDetail extends \Chaos\ORM\Model
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('description', ['type' => 'string']);

        $schema->belongsTo('gallery', Gallery::class, [
            'keys' => ['gallery_id' => 'id']
        ]);
    }
}
