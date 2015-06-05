<?php
namespace chaos\spec\fixture;

use Exception;

class Fixtures extends \chaos\spec\fixture\Fixture
{

    protected $_fixtures = [
        'gallery' => 'chaos\spec\fixture\sample\Gallery'
    ];

    public function populate($name, $methods = [])
    {
        $class = $this->_fixtures[$name];
        $instance = new $class([
            'classes' => $this->_classes,
            'connection' => $this->connection()
        ]);

        if (!$methods) {
            $methods = ['all'];
        }

        $methods = (array) $methods;

        foreach ($methods as $method) {
            $instance->{$method}();
        }
    }

    public function drop()
    {
        foreach ($this->_fixtures as $name => $class) {
            $query = $this->connection()->sql()->statement('drop table');
            $query->table($name);
            $this->connection()->execute((string) $query);
        }
    }

}
