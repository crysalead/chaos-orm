<?php
namespace chaos\spec\fixture\sample;

class Image extends \chaos\spec\fixture\Fixture
{
    protected $_source = 'image';

    protected $_schema = [
        'fields'     => [
            'id'         => ['type' => 'serial'],
            'gallery_id' => ['type' => 'integer'],
            'name'       => ['type' => 'string'],
            'title'      => ['type' => 'string', 'length' => 50]
        ]
    ];

    public function all()
    {
        $this->create();
        $this->records();
    }

    public function create()
    {
        $this->schema()->create();
    }

    public function records()
    {
        $this->populate([
            ['id' => 1, 'gallery_id' => 1, 'name' => 'someimage.png', 'title' => 'Amiga 1200'],
            ['id' => 2, 'gallery_id' => 1, 'name' => 'image.jpg', 'title' => 'Srinivasa Ramanujan'],
            ['id' => 3, 'gallery_id' => 1, 'name' => 'photo.jpg', 'title' => 'Las Vegas'],
            ['id' => 4, 'gallery_id' => 2, 'name' => 'picture.jpg', 'title' => 'Las Vegas'],
            ['id' => 5, 'gallery_id' => 2, 'name' => 'unknown.gif', 'title' => 'Unknown']
        ]);
    }
}


