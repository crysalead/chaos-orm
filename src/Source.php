<?php
namespace chaos;

/**
 * This is the base class for data abstraction layer.
 */
abstract class Source {

    /**
     * Default entity and set classes used by subclasses of `Source`.
     *
     * @var array
     */
    protected $_classes = [
        'schema' => 'chaos\source\Schema'
    ];

    /**
     * Stores configuration information for object instances at time of construction.
     *
     * @var array
     */
    protected $_config = [];

    /**
     * Stores a connection to a remote resource. Usually a database connection (`resource` type),
     * or an HTTP connection object ('object' type).
     *
     * @var mixed
     */
    protected $_connection = null;

    /**
     * Constructor. Sets defaults and returns object.
     *
     * Options defined:
     * - 'connect' `boolean` If true, a connection is made on initialization. Defaults to true.
     *
     * @param array $config
     * @return Source object
     */
    public function __construct($config = []) {
        $defaults = [
            'connect' => true,
            'classes' => $this->_classes,
            'meta' => ['key' => 'id', 'locked' => true],
            'connection' => null
        ];
        $this->_config = $config + $defaults;
        $this->_classes = $this->_config['classes'] + $this->_classes;
        $this->_connection = $this->_config['connection'];
        unset($this->_config['connection']);
        if ($this->_config['connect']) {
            $this->connect();
        }
    }


    /**
     * Return the source configuration.
     *
     * @return array.
     */
    public function config() {
        return $this->_config;
    }

    /**
     * Returns the connection.
     *
     * @return mixed
     */
    public function connection() {
        return $this->_connection;
    }

    /**
     * Checks the connection status of this data source.
     *
     * @return boolean Returns a boolean indicating whether or not the connection is currently active.
     *                 This value may not always be accurate, as the connection could have timed out or
     *                 otherwise been dropped by the remote resource during the course of the request.
     */
    public function connected() {
        return !!$this->_connection;
    }

    /**
     * Checks a specific supported feature.
     *
     * @param  string  $feature Test for support for a specific feature, i.e. `"transactions"` or
     *                 `"arrays"`.
     * @return boolean Returns `true` if the particular feature (or if MongoDB) support is enabled,
     *                 otherwise `false`.
     */
    public static function enabled($feature = null) {
        return false;
    }

    /**
     * When not supported, delegate the call to the connection.
     *
     * @param string $string
     */
    public function __call($method, $params = []) {
        return call_user_func_array([$this->_connection, $method], $params);
    }

    /**
     * Ensures the connection is closed, before the object is destroyed.
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Abstract. Must be defined by child classes.
     */
    abstract public function connect();

    /**
     * Abstract. Must be defined by child classes.
     */
    abstract public function disconnect();

    /**
     * Returns a list of objects (sources) that models can bind to, i.e. a list of tables in the
     * case of a database, or REST collections, in the case of a web service.
     *
     * @return array Returns an array of objects to which models can connect.
     */
    abstract public function sources();

    /**
     * Create a record. This is the abstract method that is implemented by specific data sources.
     * This method should take a query object and use it to create a record in the data source.
     *
     * @param mixed $query An object which defines the update operation(s) that should be performed
     *        against the data store.  This can be a `Query`, a `RecordSet`, a `Record`, or a
     *        subclass of one of the three. Alternatively, `$query` can be an adapter-specific
     *        query string.
     * @param array $options The options from Model include,
     *              - `validate` _boolean_ default: true
     *              - `events` _string_ default: create
     *              - `whitelist` _array_ default: null
     *              - `callbacks` _boolean_ default: true
     *              - `locked` _boolean_ default: true
     * @return boolean Returns true if the operation was a success, otherwise false.
     */
    //abstract public function create($query, $options = []);

    /**
     * Abstract. Must be defined by child classes.
     *
     * @param mixed $query
     * @param array $options
     * @return boolean Returns true if the operation was a success, otherwise false.
     */
    //abstract public function read($query, $options = []);

    /**
     * Updates a set of records in a concrete data store.
     *
     * @param mixed $query An object which defines the update operation(s) that should be performed
     *        against the data store.  This can be a `Query`, a `RecordSet`, a `Record`, or a
     *        subclass of one of the three. Alternatively, `$query` can be an adapter-specific
     *        query string.
     * @param array $options Options to execute, which are defined by the concrete implementation.
     * @return boolean Returns true if the update operation was a success, otherwise false.
     */
    //abstract public function update($query, $options = []);

    /**
     * Abstract. Must be defined by child classes.
     *
     * @param mixed $query
     * @param array $options
     * @return boolean Returns true if the operation was a success, otherwise false.
     */
    //abstract public function delete($query, $options = []);


    /**
     * Retrieves database error message and error code.
     *
     * @return array
     */
    abstract public function error();
}

?>