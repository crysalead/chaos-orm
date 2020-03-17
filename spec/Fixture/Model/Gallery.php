<?php
namespace Chaos\ORM\Spec\Fixture\Model;

use Chaos\ORM\Relationship;

class Gallery extends BaseModel
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('name', ['type' => 'string']);
        $schema->column('tag_ids', ['type' => 'integer', 'array' => true, 'format' => 'json', 'use' => 'json', 'default' => '[]']);

        $schema->hasOne('detail', GalleryDetail::class, [
            'keys' => ['id' => 'gallery_id']
        ]);

        $schema->hasMany('images', Image::class, [
            'keys' => ['id' => 'gallery_id']
        ]);

        $schema->hasMany('tags', Tag::class, [
            'keys' => ['tag_ids' => 'id'],
            'link' => Relationship::LINK_KEY_LIST
        ]);
    }
}
