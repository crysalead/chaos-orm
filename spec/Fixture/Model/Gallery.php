<?php
namespace Chaos\ORM\Spec\Fixture\Model;

class Gallery extends \Chaos\ORM\Model
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('name', ['type' => 'string']);

        $schema->hasOne('detail', GalleryDetail::class, [
            'keys' => ['id' => 'gallery_id']
        ]);

        $schema->hasMany('images', Image::class, [
            'keys' => ['id' => 'gallery_id']
        ]);
    }
}
