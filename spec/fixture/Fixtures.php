<?php
namespace chaos\spec\fixture;

use Exception;

class Fixtures
{
    protected $_fixtures = [
        'gallery' => 'chaos\spec\fixture\sample\Gallery'
    ];

    public function __construct($options = [])
    {
        if (!isset($options['connection'])) {
            throw new Exception("Missing connection");
        }
        $this->_connection = $options['connection'];
    }

    public function populate($name, $methods = [])
    {
        $class = $this->_fixtures[$name];
        $instance = new $class(['connection' => $this->connection()]);

        if (!$methods) {
            $methods = ['all'];
        }

        $methods = (array) $methods;

        foreach ($methods as $method) {
            $instance->{$method}();
        }
    }

    public function connection() {
        return $this->_connection;
    }

    public function drop()
    {
        $sources = $this->_connection->sources();
        foreach ($sources as $name) {
            $query = $this->connection()->sql()->statement('drop table');
            $query->table($name);
            $this->connection()->execute((string) $query);
        }
    }

}
