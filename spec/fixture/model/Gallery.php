<?php
namespace chaos\spec\fixture\model;

class Gallery extends \chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string']);

        $schema->hasOne('detail', GalleryDetail::class, [
            'keys' => ['id' => 'gallery_id']
        ]);

        $schema->hasMany('images', Image::class, [
            'keys' => ['id' => 'gallery_id']
        ]);
    }
}
