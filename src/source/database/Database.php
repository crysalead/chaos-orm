<?php
namespace chaos\source\database;

use PDO;
use PDOException;
use PDOStatement;
use set\Set;
use chaos\SourceException;

/**
 * PDO driver adapter base class
 */
abstract class Database
{
    /**
     * Default entity and set classes used by subclasses of `Source`.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Stores configuration information for object instances at time of construction.
     *
     * @var array
     */
    protected $_config = [];

    /**
     * Stores a connection to a remote resource.
     *
     * @var mixed
     */
    protected $_pdo = null;

    /**
     * Column type definitions.
     *
     * @var array
     */
    protected $_types = [];

    /**
     * Specific value denoting whether or not table aliases should be used in DELETE and UPDATE queries.
     *
     * @var boolean
     */
    protected $_alias = false;

    /**
     * The SQL dialect instance.
     *
     * @var object
     */
    protected $_dialect = null;

    /**
     * Type conversion definitions.
     *
     * @var array
     */
    protected $_handlers = [];

    /**
     * Import/export casting definitions.
     *
     * @var array
     */
    protected $_formatters = [];

    /**
     * Creates the database object and set default values for it.
     *
     * Options defined:
     *  - `'dns'`       : _string_ The full dsn connection url. Defaults to `null`.
     *  - `'database'`  : _string_ Name of the database to use. Defaults to `null`.
     *  - `'host'`      : _string_ Name/address of server to connect to. Defaults to 'localhost'.
     *  - `'login'`     : _string_ Username to use when connecting to server. Defaults to 'root'.
     *  - `'password'`  : _string_ Password to use when connecting to server. Defaults to `''`.
     *  - `'encoding'`  : _string_ The database character encoding.
     *  - `'persistent'`: _boolean_ If true a persistent connection will be attempted, provided the
     *                    adapter supports it. Defaults to `true`.
     *  - `'dialect'`       : _object_ A SQL dialect adapter
     *
     * @param  $config array Array of configuration options.
     * @return Database object.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'cursor' => 'chaos\source\database\Cursor',
                'schema' => 'chaos\source\database\Schema'
            ],
            'pdo'     => null,
            'connect' => true,
            'meta' => ['key' => 'id', 'locked' => true],
            'persistent' => true,
            'host'       => 'localhost',
            'login'      => 'root',
            'password'   => '',
            'database'   => null,
            'encoding'   => null,
            'dsn'        => null,
            'options'    => [],
            'dialect'    => null,
            'handlers'   => []
        ];
        $config = Set::merge($defaults, $config);
        $this->_config = $config + $defaults;

        $this->_classes = $this->_config['classes'] + $this->_classes;
        $this->_pdo = $this->_config['pdo'];
        unset($this->_config['pdo']);

        $this->_dialect = $config['dialect'];
        unset($this->_config['dialect']);
        $this->_handlers = Set::merge($config['handlers'], $this->_handlers());

        if ($this->_dialect === null) {
            $dialect = $this->_classes['dialect'];
            $this->_dialect = new $dialect(['connection' => $this]);
        }

        if ($this->_config['connect']) {
            $this->connect();
        }

        $handlers = $this->_handlers;

        $this->formatter('cast', 'id',        $handlers['cast']['integer']);
        $this->formatter('cast', 'serial',    $handlers['cast']['integer']);
        $this->formatter('cast', 'integer',   $handlers['cast']['integer']);
        $this->formatter('cast', 'float',     $handlers['cast']['float']);
        $this->formatter('cast', 'decimal',   $handlers['cast']['decimal']);
        $this->formatter('cast', 'date',      $handlers['cast']['date']);
        $this->formatter('cast', 'datetime',  $handlers['cast']['date']);
        $this->formatter('cast', 'boolean',   $handlers['cast']['boolean']);
        $this->formatter('cast', 'null',      $handlers['cast']['null']);
        $this->formatter('cast', '_default_', $handlers['cast']['string']);

        $this->formatter('datasource', 'id',        $handlers['cast']['string']);
        $this->formatter('datasource', 'serial',    $handlers['cast']['string']);
        $this->formatter('datasource', 'integer',   $handlers['cast']['string']);
        $this->formatter('datasource', 'float',     $handlers['cast']['string']);
        $this->formatter('datasource', 'decimal',   $handlers['cast']['string']);
        $this->formatter('datasource', 'date',      $handlers['datasource']['date']);
        $this->formatter('datasource', 'datetime',  $handlers['datasource']['date']);
        $this->formatter('datasource', 'boolean',   $handlers['datasource']['boolean']);
        $this->formatter('datasource', 'null',      $handlers['datasource']['null']);
        $this->formatter('datasource', '_default_', $handlers['datasource']['quote']);
    }

    /**
     * When not supported, delegate the call to the connection.
     *
     * @param string $name   The name of the matcher.
     * @param array  $params The parameters to pass to the matcher.
     */
    public function __call($name, $params = [])
    {
        return call_user_func_array([$this->_pdo, $name], $params);
    }

    /**
     * Return the source configuration.
     *
     * @return array.
     */
    public function config()
    {
        return $this->_config;
    }

    /**
     * Get database connection.
     *
     * @return object PDO
     */
    public function connect()
    {
        if ($this->_pdo) {
            return $this->_pdo;
        }
        $config = $this->_config;

        if (!$config['database']) {
            throw new PDOException('No Database configured');
        }
        if (!$config['dsn']) {
            throw new PDOException('No DSN setup for DB Connection');
        }
        $dsn = $config['dsn'];

        $options = $config['options'] + [
            PDO::ATTR_PERSISTENT => $config['persistent'],
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {
            $this->_pdo = new PDO($dsn, $config['login'], $config['password'], $options);
        } catch (PDOException $e) {
            $this->_exception($e);
        }

        if ($config['encoding']) {
            $this->encoding($config['encoding']);
        }

        return $this->_pdo;
    }

    /**
     * Returns the SQL dialect instance.
     *
     * @return object.
     */
    public function dialect() {
        return $this->_dialect;
    }

    /**
     * Returns the pdo connection instance.
     *
     * @return mixed
     */
    public function driver() {
        return $this->_pdo;
    }

    /**
     * Checks the connection status of this data source.
     *
     * @return boolean Returns a boolean indicating whether or not the connection is currently active.
     *                 This value may not always be accurate, as the connection could have timed out or
     *                 otherwise been dropped by the remote resource during the course of the request.
     */
    public function connected() {
        return !!$this->_pdo;
    }

    /**
     * PDOException wrapper
     *
     * @param  PDOException $e A PDOException.
     * @throws chaos\SourceException
     */
    protected function _exception($e)
    {
        $config = $this->_config;
        $code = $e->getCode();
        $msg = $e->getMessage();
        switch (true) {
            case $code === 'HY000' || substr($code, 0, 2) === '08':
                $msg = "Unable to connect to host `{$config['host']}`.";
            break;
            case in_array($code, array('28000', '42000')):
                $msg = "Host connected, but could not access database `{$config['database']}`.";
            break;
        }
        throw new SourceException($msg, $code, $e);
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    protected function _sources($sql)
    {
        $result = $this->query($sql->toString());

        $sources = [];
        foreach($result as $source) {
            $name = reset($source);
            $sources[$name] = $name;
        }
        return $sources;
    }

    /**
     * Return default cast handlers
     *
     * @return array
     */
    protected function _handlers()
    {
        return [
            'cast' => [
                'string' => function($value, $params = []) {
                    return (string) $value;
                },
                'integer' => function($value, $params = []) {
                    return (integer) $value;
                },
                'float'   => function($value, $params = []) {
                    return (float) $value;
                },
                'decimal' => function($value, $params = []) {
                    $params += ['precision' => 2];
                    return number_format($number, $params['precision']);
                },
                'boolean' => function($value, $params = []) {
                    return !!$value;
                },
                'date'    => function($value, $params = []) {
                    if (is_numeric($value)) {
                        return new DateTime('@' . $value);
                    }
                    return DateTime::createFromFormat($this->_dateFormat, $value);
                },
                'null'    => function($value, $params = []) {
                    return null;
                }
            ],
            'datasource' => [
                'string' => function($value, $params = []) {
                    return (string) $value;
                },
                'quote' => function($value, $params = []) {
                    return $this->dialect()->quote((string) $value);
                },
                'date' => function($value, $params = []) {
                    $params += ['format' => null];
                    $format = $params['format'];
                    if (!$format) {
                        throw new SourceException("Invalid date format:`{$format}`.");
                    }
                    if ($value instanceof DateTime) {
                        return $this->dialect()->quote($value->format($format));
                    } else {
                        throw new SourceException("Invalid date, must be an instance of `DateTime`.");
                    }
                },
                'boolean' => function($value, $params = []) {
                    return $value ? 'TRUE' : 'FALSE';
                },
                'null'    => function($value, $params = []) {
                    return 'NULL';
                }
            ]
        ];
    }

    /**
     * Get/set an internal type definition
     *
     * @param  string $type   The type name.
     * @param  array  $config The type definition.
     * @return array          Return the type definition.
     */
    public function type($type, $config = null)
    {
        if ($config) {
            $this->_types[$type] = $config;
        }
        if (!isset($this->_types[$type])) {
            throw new SourceException("Column type `'{$type}'` does not exist.");
        }
        return $this->_types[$type];
    }

    /**
     * Gets/sets a formatter handler.
     *
     * @param  string   $type          The type name.
     * @param  callable $importHandler The callable import handler.
     * @param  callable $exportHandler The callable export handler. If not set use `$importHandler`.
     */
    public function formatter($mode, $type, $handler = null)
    {
        if (func_num_args() === 2) {
            return isset($this->_formatters[$mode][$type]) ? $this->_formatters[$mode][$type] : $this->_formatters[$mode]['_default_'];
        }
        $this->_formatters[$mode][$type] = $handler;
        return $this;
    }

    /**
     * Gets/sets all formatters.
     *
     */
    public function formatters($formatters = null)
    {
        if (!func_num_args()) {
            return $this->_formatters;
        }
        $this->_formatters = $formatters;
        return $this;
    }

    /**
     * Formats a value according to its definition.
     *
     * @param   string $mode  The format mode (i.e. `'cast'` or `'datasource'`).
     * @param   string $type  The type name.
     * @param   mixed  $value The value to format.
     * @return  mixed         The formated value.
     */
    public function format($mode, $type, $value)
    {
        if ($value === null) {
            $type = 'null';
        }

        $formatter = null;

        if (isset($this->_formatters[$mode][$type])) {
            $formatter = $this->_formatters[$mode][$type];
        } elseif (isset($this->_formatters[$mode]['_default_'])) {
            $formatter = $this->_formatters[$mode]['_default_'];
        }
        if (!$formatter) {
            throw new SourceException("Unexisting formatter for `{$type}` type using `{$mode}` handlers.");
        }
        $formatter = isset($this->_formatters[$mode][$type]) ? $this->_formatters[$mode][$type] : $this->_formatters[$mode]['_default_'];
        return $formatter($value);
    }

    /**
     * Finds records using a SQL query.
     *
     * @param  string $sql  SQL query to execute.
     * @param  array  $data Array of bound parameters to use as values for query.
     * @return object       A `Cursor` instance.
     */
    public function query($sql, $data = [], $options = [])
    {
        $statement = $this->_pdo->prepare($sql);

        try {
            $error = !$statement->execute($data);
        } catch (Exception $e) {
            $error = true;
        }

        $err = $statement->errorInfo();
        $errmsg = $err[0] === '0000' ? '' : $err[0] . ($err[1] ? ' (' . $err[1] . ')' : '') . ':' . $err[2];

        $cursor = $this->_classes['cursor'];

        return new $cursor($options + [
            'resource' => $statement,
            'failed'   => $error,
            'errno'    => $err[0],
            'errmsg'   => $errmsg
        ]);
    }

    /**
     * Get the last insert id from the database.
     *
     * @param $query lithium\data\model\Query $context The given query.
     */
    public function lastInsertId($source = null, $field = null)
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * Retrieves database error message and error code.
     *
     * @return array
     */
    public function error()
    {
        if ($error = $this->_pdo->errorInfo()) {
            return [$error[1], $error[2]];
        }
    }

    /**
     * Disconnects the adapter from the database.
     *
     * @return boolean Returns `true` on success, else `false`.
     */
    public function disconnect()
    {
        $this->_pdo = null;
        return true;
    }

    /**
     * Getter/Setter for the connection's encoding
     * Abstract. Must be defined by child class.
     *
     * @param mixed $encoding
     * @return mixed.
     */
    abstract public function encoding($encoding = null);
}
