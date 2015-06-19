<?php
namespace chaos\spec\fixture\schema;

class Image extends \chaos\spec\fixture\Fixture
{
    public $_model = 'chaos\spec\fixture\model\Image';

    public function all()
    {
        $this->create();
        $this->records();
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
