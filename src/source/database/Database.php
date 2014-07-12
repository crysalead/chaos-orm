<?php
namespace chaos\source\database;

use PDO;
use PDOException;
use PDOStatement;
use set\Set;
use chaos\SourceException;

/**
 * Base PDO adapter
 */
abstract class Database extends \chaos\Source
{
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
    protected $_sql = null;

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
     *  - `'sql'`       : _object_ A SQL dialect adapter
     *
     * @param  $config array Array of configuration options.
     * @return Database object.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'result' => 'chaos\source\database\Result'
            ],
            'persistent' => true,
            'host'       => 'localhost',
            'login'      => 'root',
            'password'   => '',
            'database'   => null,
            'encoding'   => null,
            'dsn'        => null,
            'options'    => [],
            'sql'        => null,
            'handlers'   => []
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);

        $this->_sql = $config['sql'];
        unset($this->_config['sql']);
        $this->_handlers = $config['handlers'];

        if ($this->_sql === null) {
            $sql = $this->_classes['sql'];
            $this->_sql = new $sql(['adapter' => $this]);
        }
    }

    /**
     * Get database connection.
     *
     * @return object PDO
     */
    public function connect()
    {
        if ($this->_connection) {
            return $this->_connection;
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
            $this->_connection = new PDO($dsn, $config['login'], $config['password'], $options);
        } catch (PDOException $e) {
            $this->_connectError($e);
        }

        if ($config['encoding']) {
            $this->encoding($config['encoding']);
        }

        return $this->_connection;
    }

    /**
     * Manage connection error
     *
     * @param  PDOException $e A PDOException.
     * @throws chaos\SourceException
     */
    protected function _connectError($e)
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
     * Returns the SQL dialect instance.
     *
     * @return object.
     */
    public function sql() {
        return $this->_sql;
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    protected function _sources($sql)
    {
        $statement = $this->_connection->query($sql);
        $result = $statement->fetchAll(PDO::FETCH_NUM);

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
            'toDecimal' => function($value, $params = []) {
                $params += ['precision' => 2];
                return number_format($number, $params['precision']);
            },
            'importDate'     => function($value, $params = []) {
                if (is_numeric($value)) {
                    return new DateTime('@' . $value);
                }
                return DateTime::createFromFormat($this->_dateFormat, $value);
            },
            'exportDate' => function($value, $params = []) {
                $params += ['format' => null];
                $format = $params['format'];
                if ($format) {
                    if ($value instanceof DateTime) {
                        return $value->format($format);
                    } elseif(($time = strtotime($value)) !== false) {
                        return date($format, $time);
                    }
                }
                throw new Exception("Invalid date value: `{$value}`.");
            },
            'importBoolean' => function($value, $params = []) { return $value ? 1 : 0; },
            'exportBoolean' => function($value, $params = []) { return !!$value; }
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
            throw new SourceException("Column type `{$type}` does not exist.");
        }
        return $this->_types[$type];
    }

    /**
     * Get/set an internal type casting definition
     *
     * @param  string   $type          The type name.
     * @param  callable $importHandler The callable import handler.
     * @param  callable $exportHandler The callable export handler. If not set use `$importHandler`.
     */
    public function format($type, $importHandler = null, $exportHandler = null)
    {
        switch(func_num_args()) {
            case 1:
                $handlers = [];
                if (isset($this->_formatters['import'][$type])) {
                    $handlers['import'] = $this->_formatters['import'][$type];
                }
                if (isset($this->_formatters['export'][$type])) {
                    $handlers['export'] = $this->_formatters['export'][$type];
                }
                return $handlers + ['import' => 'strval', 'export' => 'strval'];
            break;
            case 2:
                $exportHandler = $importHandler;
            case 3:
                $this->_format('import', $type, $importHandler);
                $this->_format('export', $type, $exportHandler);
            break;
        }
    }

    /**
     * Set an type format definition.
     *
     * @param  string   $mode    The format mode (i.e. `'import'` or `'export'`).
     * @param  string   $type    The type name.
     * @param  callable $handler The callable handler.
     */
    public function _format($mode, $type, $handler)
    {
        if (is_callable($handler)) {
            $this->_formatters[$mode][$type] = $handler;
            return;
        }
        if (!isset($this->_handlers[$handler])) {
            throw new SourceException("Invalid cast handler `{$handler}`.");
        }
        $this->_formatters[$mode][$type] = $handler;
    }

    /**
     * Cast a value according to a type definition.
     *
     * @param   string $mode  The format mode (i.e. `'import'` or `'export'`).
     * @param   string $type  The type name.
     * @param   mixed  $value The value to cast.
     * @return  mixed         The casted value.
     */
    public function cast($mode, $type, $value) {
        $formatter = isset($this->_formatters[$mode][$type]) ? $this->_formatters[$mode][$type] : 'strval';
        return $formatter($value);
    }

    public function read($data = []) {
        $defaults = [
            'source' => null,
            'fields' => [],
            'alias' => null,
            'joins' => [],
            'conditions' => [],
            'group' => [],
            'having' => [],
            'order' => [],
            'offset' => null,
            'limit' => null,
            'comment' => null
        ];

        $data += $defaults;

        $query  = $this->select($data['fields']);
        $query .= $this->from($data['source'], $data['alias']);
        foreach ($data['joins'] as $join) {
            $query .= $this->join($join);
        }
        $query .= $this->where($data['where']);
        $query .= $this->group($data['group']);
        $query .= $this->having($data['having']);
        $query .= $this->order($data['order']);
        $query .= $this->limit($data['limit']);
        $query .= $this->comment($data['comment']);
    }

    public function update($data) {
    }

    public function delete($data) {

    }

    /**
     * Find records with custom SQL query.
     *
     * @param  string       $sql  SQL query to execute.
     * @param  array        $data Array of bound parameters to use as values for query.
     * @return PDOStatement A PDOStatement
     * @throws chaos\SourceException
     */
    public function execute($sql, $data = []){
        $statement = $this->_connection->prepare($sql);
        $statement->execute($data);
        return $statement;
    }

    /**
     * Get the last insert id from the database.
     * Abstract. Must be defined by child class.
     *
     * @param $query lithium\data\model\Query $context The given query.
     */
    public function lastInsertId($source = null, $field = null) {
        return $this->_connection->lastInsertId();
    }

    /**
     * Create a database-native table
     *
     * @param string $name A table name.
     * @param object $schema A `Schema` instance.
     * @return boolean `true` on success, `true` otherwise
     */
    public function createSchema($source, $schema) {
        if (!$schema instanceof $this->_classes['schema']) {
            throw new SourceException("Passed schema is not a valid `{$this->_classes['schema']}` instance.");
        }
        $query = $this->sql()->statement('create table');
        $query->table($source)
            ->columns($schema->fields())
            ->constraints($schema->meta());

        echo $query->toString();
    }

    /**
     * Drop a table
     *
     * @param string $name The table name to drop.
     * @param boolean $soft With "soft dropping", the function will retrun `true` even if the
     *                table doesn't exists.
     * @return boolean `true` on success, `false` otherwise
     */
    public function dropSchema($source, $soft = true) {
        $source = $this->escape($source);
        $exists = $soft ? 'IF EXISTS ' : '';
        return !!$this->execute("DROP TABLE {$exists}{$source}");
    }

    public function create($data) {
        $defaults = [
            'source' => null,
            'fields' => [],
            'values' => [],
            'comment' => null
        ];

        $data += $defaults;

        $query  = $this->insert($data['source'], $data['fields'], $data['values']);
        $query .= $this->comment($data['comment']);
    }

    /**
     * Retrieves database error message and error code.
     *
     * @return array
     */
    public function error() {
        if ($error = $this->_connection->errorInfo()) {
            return [$error[1], $error[2]];
        }
    }

    /**
     * Disconnects the adapter from the database.
     *
     * @return boolean Returns `true` on success, else `false`.
     */
    public function disconnect() {
        $this->_connection = null;
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

?>