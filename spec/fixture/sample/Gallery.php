<?php
namespace chaos\spec\fixture\sample;

class Gallery extends \chaos\spec\fixture\Fixture
{
    protected $_source = 'gallery';

    protected $_schema = [
        'fields'     => [
            'id'   => ['type' => 'serial'],
            'name' => ['type' => 'string']
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
            ['id' => 1, 'name' => 'Foo Gallery'],
            ['id' => 2, 'name' => 'Bar Gallery']
        ]);
    }
}
