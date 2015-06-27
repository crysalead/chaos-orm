<?php
namespace chaos\spec\fixture\model;

class GalleryDetail extends \chaos\model\Model
{
    protected static $_schema = 'chaos\source\database\Schema';

    protected static function _meta()
    {
        return ['source' => 'gallery_detail'];
    }

    protected static function _schema($schema)
    {
        $schema->set('id', ['type' => 'serial']);
        $schema->set('description', ['type' => 'string']);
        $schema->set('gallery_id', ['type' => 'integer']);

        $schema->bind('gallery', [
            'relation' => 'belongsTo',
            'to'       => 'chaos\spec\fixture\model\Gallery',
            'keys'     => ['gallery_id' => 'id']
        ]);
    }
}
