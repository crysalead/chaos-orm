<?php
namespace chaos\spec\fixture\model;

class Tag extends \chaos\Model
{
    protected static function _define($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('name', ['type' => 'string', 'length' => 50]);

        $schema->bind('images_tags', [
            'relation' => 'hasMany',
            'to'       => 'chaos\spec\fixture\model\ImageTag',
            'key'      => ['id' => 'tag_id']
        ]);

        $schema->bind('images', [
            'relation' => 'hasManyThrough',
            'through'  => 'images_tags',
            'using'    => 'image'
        ]);
    }

}
