<?php
namespace chaos\spec\fixture\model;

class Gallery extends \chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->bind('detail', [
            'relation' => 'hasOne',
            'to'       => 'chaos\spec\fixture\model\GalleryDetail',
            'keys'     => ['id' => 'gallery_id']
        ]);

        $schema->bind('images', [
            'relation' => 'hasMany',
            'to'       => 'chaos\spec\fixture\model\Image',
            'keys'     => ['id' => 'gallery_id']
        ]);
    }
}
