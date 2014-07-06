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
     * Specific value denoting whether or not table aliases should be used in DELETE and UPDATE queries.
     *
     * @var boolean
     */
    protected $_alias = false;

    /**
     * The SQL dialect instance
     *
     * @var object
     */
    protected $_sql = null;

    /**
     * Creates the database object and set default values for it.
     *
     * Options defined:
     *  - 'database' _string_ Name of the database to use. Defaults to `null`.
     *  - 'host' _string_ Name/address of server to connect to. Defaults to 'localhost'.
     *  - 'login' _string_ Username to use when connecting to server. Defaults to 'root'.
     *  - 'password' _string_ Password to use when connecting to server. Defaults to `''`.
     *  - 'persistent' _boolean_ If true a persistent connection will be attempted, provided the
     *    adapter supports it. Defaults to `true`.
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
            'sql'    => null
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);

        $this->_sql = $config['sql'];
        unset($this->_config['sql']);

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
        $query .= $this->where($data['joins']);
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
        $query->table($source);
            //->columns($schema->fields())
            //->constraints($schema->meta());


        echo $query;

        $columns = [];
        $primary = null;

        $source = $this->sql()->escape($source);

        foreach ($schema->fields() as $name => $field) {
            $field['name'] = $name;
            if ($field['type'] === 'id') {
                $primary = $name;
            }
            $columns[] = $this->column($field);
        }
        $columns = join(",\n", array_filter($columns));

        $metas = $schema->meta() + ['table' => [], 'constraints' => []];

        $constraints = $this->sql()->constraints($metas['constraints'], ",\n", $primary);
        $table = $this->sql()->metas('table', $metas['table']);
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