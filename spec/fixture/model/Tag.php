<?php
namespace chaos\spec\fixture\model;

class Tag extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    protected static function _schema($schema)
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
