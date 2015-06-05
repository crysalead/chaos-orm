<?php
namespace chaos\spec\fixture\sample;

class Gallery extends \chaos\spec\fixture\Fixture
{
    public function all()
    {
        $this->table();
    }

    public function table() {

        $schema = $this->_classes['schema'];
        $gallery = new $schema();

        $gallery
            ->connection($this->connection())
            ->source('gallery')
            ->add('id', ['type' => 'id'])
            ->add('name', ['type' => 'string'])
            ->create();
    }
}
