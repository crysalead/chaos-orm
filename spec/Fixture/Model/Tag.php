<?php
namespace Chaos\ORM\Spec\Fixture\Model;

class Tag extends BaseModel
{
    protected static function _define($schema)
    {
        $schema->column('id', ['type' => 'serial']);
        $schema->column('name', ['type' => 'string', 'length' => 50]);

        $schema->hasMany('images_tags', ImageTag::class, [
            'key' => ['id' => 'tag_id']
        ]);

        $schema->hasManyThrough('images', 'images_tags', 'image');
    }

}
