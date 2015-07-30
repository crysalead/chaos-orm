<?php
namespace chaos\spec\fixture\model;

class GalleryDetail extends \chaos\Model
{
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
