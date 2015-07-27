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
     * @param  string  $feature Test for support for a specific feature, i.e. `"transactions"`
     *                          or `"arrays"`.
     * @return boolean          Returns `true` if the particular feature (or if MySQL) support
     *                          is enabled, otherwise `false`.
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
            'sources' => true
        ];
        return isset($features[$feature]) ? $features[$feature] : null;
    }

    /**
     * Constructs the MySQL adapter and sets the default port to 3306.
     *
     * @param array $config Configuration options for this class. Available options
     *                      defined by this class:
     *                      - `'host'`: _string_ The IP or machine name where MySQL is running,
     *                                  followed by a colon, followed by a port number or socket.
     *                                  Defaults to `'localhost:3306'`.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'host' => 'localhost:3306',
            'classes' => [
                'dialect' => 'sql\dialect\MySql'
            ],
            'handlers' => [],
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }

    /**
     * Connects to the database using the options provided to the class constructor.
     *
     * @return boolean Returns `true` if a database connection could be established,
     *                 otherwise `false`.
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

        $info = $this->_pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $this->_alias = (boolean) version_compare($info, "4.1", ">=");
        return true;
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    public function sources() {
        $select = $this->dialect()->statement('select');
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
     * @param  mixed  $name   Specifies the table name for which the schema should be returned.
     * @param  array  $fields Any schema data pre-defined by the model.
     * @param  array  $meta
     * @return object         Returns a shema definition.
     */
    public function describe($name,  $fields = [], $meta = [])
    {
        $schema = $this->_classes['schema'];

        if (func_num_args() === 1) {
            $columns = $this->query("DESCRIBE {$name}");

            foreach ($columns as $column) {
                $field = $this->_column($column['Type']);
                $default = $column['Default'];

                if ($default === 'CURRENT_TIMESTAMP') {
                    $default = null;
                } elseif ($column['Type'] === 'boolean') {
                    $default = !!$default;
                }
                $fields[$column['Field']] = $field + [
                    'null'     => ($column['Null'] === 'YES' ? true : false),
                    'default'  => $default
                ];
            }
        }

        return new $schema([
            'connection' => $this,
            'source'     => $name,
            'fields'     => $fields,
            'meta'        => $meta
        ]);
    }

    /**
     * Converts database-layer column types to basic types.
     *
     * @param  string $real Real database-layer column type (i.e. `"varchar(255)"`)
     * @return array        Column type (i.e. "string") plus 'length' when appropriate.
     */
    protected function _column($real)
    {
        if (is_array($real)) {
            return $real['type'] . (isset($real['length']) ? "({$real['length']})" : '');
        }

        if (!preg_match('/(?P<type>\w+)(?:\((?P<length>[\d,]+)\))?/', $real, $column)) {
            return $real;
        }
        $column = array_intersect_key($column, ['type' => null, 'length' => null]);

        if (isset($column['length']) && $column['length']) {
            $length = explode(',', $column['length']) + [null, null];
            $column['length'] = $length[0] ? intval($length[0]) : null;
            $length[1] ? $column['precision'] = intval($length[1]) : null;
        }

        $column['type'] = $this->dialect()->typeMatch($column['type']);
        return $column;
    }

    /**
     * Gets or sets the encoding for the connection.
     *
     * @param  $encoding
     * @return mixed     If setting the encoding; returns true on success, else false.
     *                   When getting, returns the encoding.
     */
    public function encoding($encoding = null)
    {
        $encodingMap = ['UTF-8' => 'utf8'];

        if (empty($encoding)) {
            $query = $this->_pdo->query("SHOW VARIABLES LIKE 'character_set_client'");
            $encoding = $query->fetchColumn(1);
            return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
        }
        $encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;

        try {
            $this->_pdo->exec("SET NAMES '{$encoding}'");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
