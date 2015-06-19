<?php
namespace chaos\spec\fixture\schema;

class Gallery extends \chaos\spec\fixture\Fixture
{
    public $_model = 'chaos\spec\fixture\model\Gallery';

    public function all()
    {
        $this->create();
        $this->records();
    }

    public function records()
    {
        $this->populate([
            ['id' => 1, 'name' => 'Foo Gallery'],
            ['id' => 2, 'name' => 'Bar Gallery']
        ]);
    }
}
