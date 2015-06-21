<?php
namespace chaos\spec\fixture\model;

class ImageTag extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    protected static function _meta()
    {
        return ['source' => 'image_tag'];
    }

    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('image_id', ['type' => 'integer']);
        $schema->set('tag_id', ['type' => 'integer']);

        $schema->bind('image', [
            'relation' => 'belongsTo',
            'to'       => 'chaos\spec\fixture\model\Image',
            'keys'     => ['image_id' => 'id']
        ]);

        $schema->bind('tag', [
            'relation' => 'belongsTo',
            'to'       => 'chaos\spec\fixture\model\Tag',
            'keys'     => ['tag_id' => 'id']
        ]);
    }
}
