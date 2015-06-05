<?php
namespace chaos\spec\fixture;

use Exception;
use set\Set;

class Fixture
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The connection to the datasource.
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * Constructor.
     *
     * @param array $config Possible options are:
     *                      - `'classes'`     _array_  : The class dependencies.
     *                      - `'connection'`  _object_ : The connection instance.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes'      => [
                'schema'       => 'chaos\source\database\Schema'
            ],
            'connection'   => null
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_connection = $config['connection'];
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    Returns a connection instance.
     * @throws Exception Throws a `chaos\SourceException` if a connection isn't set.
     */
    public function connection($connection = null)
    {
        if (func_num_args()) {
            return $this->_connection = $connection;
        }
        if (!$this->_connection) {
            throw new SourceException("Error, missing connection for this schema.");
        }
        return $this->_connection;
    }

}
