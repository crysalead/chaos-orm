<?php
namespace chaos\spec\fixture\model;

class GalleryDetail extends \chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('description', ['type' => 'string']);
        $schema->set('gallery_id', ['type' => 'integer']);

        $schema->belongsTo('gallery', Gallery::class, [
            'keys' => ['gallery_id' => 'id']
        ]);
    }
}
