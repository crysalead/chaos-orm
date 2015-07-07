<?php
namespace chaos\spec\fixture;

use set\Set;
use Exception;
use chaos\SourceException;

class Fixtures
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
     * The fixtures data.
     *
     * @var array
     */
    protected $_fixtures = [];

    /**
     * The created instances.
     *
     * @var array
     */
    protected $_instances = [];

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
                'schema' => 'chaos\source\database\Schema',
                'model'  => 'chaos\model\Model'
            ],
            'connection'   => null,
            'fixtures'     => []
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_connection = $config['connection'];
        $this->_fixtures = $config['fixtures'];
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    $connection Returns a connection instance.
     * @throws Exception             Throws a `chaos\SourceException` if a connection isn't set.
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

    /**
     * Returns a fixture instance.
     *
     * @return string $name    The name of the fixture to get.
     * @return object          The fixture instance.
     */
    public function get($name)
    {
        if (isset($this->_instances[$name])) {
            return $this->_instances[$name];
        }
        if (!isset($this->_fixtures[$name])) {
            throw new SourceException("Error, the fixture `'{$name}'` hasn't been defined.");
        }
        $fixture = $this->_fixtures[$name];

        return $this->_instances[$name] = new $fixture([
            'classes' => $this->_classes,
            'connection' => $this->connection(),
            'fixtures' => $this
        ]);
    }

    /**
     * Gets alter definitions or sets a new one.
     *
     * @param  string $mode The type of alteration.
     * @param  string $key  The field name to alter.
     * @return              The alter definitions or `null` in set mode.
     */
    public function alter($mode = null, $key = null, $value = [])
    {
        if ($mode === null) {
            return $this->_alters;
        }
        if ($key && $mode === 'drop') {
            $this->_alters['drop'][] = $key;
            return;
        }
        if ($key && $value) {
            $this->_alters[$mode][$key] = $value;
        }
    }

    /**
     * Populates some fixtures.
     *
     * @return string $name    The name of the fixture to populate in the datasource.
     * @return array  $methods An array of method to run (default: `['all']`).
     */
    public function populate($name, $methods = [])
    {
        $fixture = $this->get($name);

        if (!$methods) {
            $methods = ['all'];
        }

        $methods = (array) $methods;

        foreach ($methods as $method) {
            $fixture->{$method}();
        }
    }

    /**
     * Truncates all populated fixtures.
     */
    public function truncate()
    {
        foreach ($this->_instances as $instance) {
            $model = $instance->model();
            $model::remove();
        }
    }

    /**
     * Drops all populated fixtures.
     */
    public function drop()
    {
        foreach ($this->_instances as $instance) {
            $instance->drop();
        }
    }

    public function reset()
    {
        foreach ($this->_instances as $instance) {
            $model = $instance->model();
            $model::config();
        }
        $this->_instances = [];
    }
}
