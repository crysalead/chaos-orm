<?php
namespace Chaos\Spec\Fixture\Model;

class GalleryDetail extends \Chaos\Model
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('description', ['type' => 'string']);
        $schema->column('gallery_id', ['type' => 'integer']);

        $schema->belongsTo('gallery', Gallery::class, [
            'keys' => ['gallery_id' => 'id']
        ]);
    }
}
