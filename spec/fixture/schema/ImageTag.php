<?php
namespace chaos\spec\fixture\schema;

class ImageTag extends \chaos\spec\fixture\Fixture
{
    public $_model = 'chaos\spec\fixture\model\ImageTag';

    public function all()
    {
        $this->create();
        $this->records();
    }

    public function records()
    {
        $this->populate([
            ['id' => 1, 'image_id' => 1, 'tag_id' => 1],
            ['id' => 2, 'image_id' => 1, 'tag_id' => 3],
            ['id' => 3, 'image_id' => 2, 'tag_id' => 5],
            ['id' => 4, 'image_id' => 3, 'tag_id' => 6],
            ['id' => 5, 'image_id' => 4, 'tag_id' => 6],
            ['id' => 6, 'image_id' => 4, 'tag_id' => 3],
            ['id' => 7, 'image_id' => 4, 'tag_id' => 1]
        ]);
    }
}
