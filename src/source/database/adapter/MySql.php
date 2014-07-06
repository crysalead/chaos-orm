<?php
namespace chaos\source\database\adapter;

use PDO;
use set\Set;

/**
 * MySQL adapter
 */
class MySql extends \chaos\source\database\Database
{
    /**
     * Check for required PHP extension, or supported database feature.
     *
     * @param  string $feature Test for support for a specific feature, i.e. `"transactions"`
     *                or `"arrays"`.
     * @return boolean Returns `true` if the particular feature (or if MySQL) support is enabled,
     *         otherwise `false`.
     */
    public static function enabled($feature = null)
    {
        if (!$feature) {
            return extension_loaded('pdo_mysql');
        }
        $features = [
            'arrays' => false,
            'transactions' => true,
            'booleans' => true,
            'schema' => true,
            'relationships' => true,
            'sources' => true
        ];
        return isset($features[$feature]) ? $features[$feature] : null;
    }

    /**
     * Constructs the MySQL adapter and sets the default port to 3306.
     *
     * @param array $config Configuration options for this class. For additional configuration,
     *        see `lithium\data\source\Database` and `lithium\data\Source`. Available options
     *        defined by this class:
     *        - `'database'`: The name of the database to connect to. Defaults to 'lithium'.
     *        - `'host'`: The IP or machine name where MySQL is running, followed by a colon,
     *          followed by a port number or socket. Defaults to `'localhost:3306'`.
     *        - `'persistent'`: If a persistent connection (if available) should be made.
     *          Defaults to true.
     *        Typically, these parameters are set in `Connections::add()`, when adding the
     *        adapter to the list of active connections.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'host' => 'localhost:3306',
            'encoding' => null,
            'classes' => [
                'sql' => 'chaos\source\database\sql\dialect\MySqlDialect'
            ]
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }

    /**
     * Connects to the database using the options provided to the class constructor.
     *
     * @return boolean Returns `true` if a database connection could be established, otherwise
     *         `false`.
     */
    public function connect()
    {
        if (!$this->_config['dsn']) {
            $host = $this->_config['host'];
            list($host, $port) = explode(':', $host) + [1 => "3306"];
            $dsn = "mysql:host=%s;port=%s;dbname=%s";
            $this->_config['dsn'] = sprintf($dsn, $host, $port, $this->_config['database']);
        }

        if (!parent::connect()) {
            return false;
        }

        $info = $this->_connection->getAttribute(PDO::ATTR_SERVER_VERSION);
        $this->_alias = (boolean) version_compare($info, "4.1", ">=");
        return true;
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    public function sources() {
        $select = $this->sql()->statement('select');
        $select->fields('table_name')
            ->from(['information_schema' => ['tables']])
            ->where([
               'table_type' => 'BASE TABLE',
               'table_schema' => $this->_config['database']
            ]);
        return $this->_sources($select);
    }

    /**
     * Gets the column schema for a given MySQL table.
     *
     * @param mixed $entity Specifies the table name for which the schema should be returned, or
     *        the class name of the model object requesting the schema, in which case the model
     *        class will be queried for the correct table name.
     * @param array $fields Any schema data pre-defined by the model.
     * @param array $meta
     * @return array Returns an associative array describing the given table's schema, where the
     *         array keys are the available fields, and the values are arrays describing each
     *         field, containing the following keys:
     *         - `'type'`: The field type name
     * @filter This method can be filtered.
     */
    public function describe($entity,  $fields = [], array $meta = [])
    {
        $params = compact('entity', 'meta', 'fields');
        return $this->_filter(__METHOD__, $params, function($self, $params) {
            extract($params);

            if ($fields) {
                return $self->invokeMethod('_instance', array('schema', compact('fields')));
            }
            $name = $self->invokeMethod('_entityName', array($entity, array('quoted' => true)));
            $columns = $self->read("DESCRIBE {$name}", array('return' => 'array', 'schema' => array(
                'field', 'type', 'null', 'key', 'default', 'extra'
            )));
            $fields = array();

            foreach ($columns as $column) {
                $schema = $self->invokeMethod('_column', array($column['type']));
                $default = $column['default'];

                if ($default === 'CURRENT_TIMESTAMP') {
                    $default = null;
                } elseif ($schema['type'] === 'boolean') {
                    $default = !!$default;
                }
                $fields[$column['field']] = $schema + array(
                    'null'     => ($column['null'] === 'YES' ? true : false),
                    'default'  => $default
                );
            }
            return $self->invokeMethod('_instance', array('schema', compact('fields')));
        });
    }

    /**
     * Gets or sets the encoding for the connection.
     *
     * @param $encoding
     * @return mixed If setting the encoding; returns true on success, else false.
     *         When getting, returns the encoding.
     */
    public function encoding($encoding = null)
    {
        $encodingMap = ['UTF-8' => 'utf8'];

        if (empty($encoding)) {
            $query = $this->_connection->query("SHOW VARIABLES LIKE 'character_set_client'");
            $encoding = $query->fetchColumn(1);
            return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
        }
        $encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;

        try {
            $this->_connection->exec("SET NAMES '{$encoding}'");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Retrieves database error message and error code.
     *
     * @return array
     */
    public function error()
    {
        if ($error = $this->_connection->errorInfo()) {
            return [$error[1], $error[2]];
        }
    }

    /**
     * @todo Eventually, this will need to rewrite aliases for DELETE and UPDATE queries, same with
     *       order().
     * @param string $conditions
     * @param string $context
     * @param array $options
     * @return void
     */
    public function conditions($conditions, $context, $options = [])
    {
        return parent::conditions($conditions, $context, $options);
    }

    /**
     * Execute a given query.
     *
     * @see lithium\data\source\Database::renderCommand()
     * @param string $sql The sql string to execute
     * @param array $options Available options:
     *        - 'buffered': If set to `false` uses mysql_unbuffered_query which
     *          sends the SQL query query to MySQL without automatically fetching and buffering the
     *          result rows as `mysql_query()` does (for less memory usage).
     * @return resource Returns the result resource handle if the query is successful.
     * @filter
     */
    protected function _execute($sql, $options = [])
    {
        $defaults = ['buffered' => true];
        $options += $defaults;

        $conn = $this->_connection;
        $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $options['buffered']);

        try {
            $resource = $conn->query($sql);
        } catch(PDOException $e) {
            $self->invokeMethod('_error', [$sql]);
        };
        return $self->invokeMethod('_instance', ['result', compact('resource')]);
    }
}
