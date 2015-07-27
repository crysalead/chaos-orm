<?php
namespace chaos\source\database\adapter;

use set\Set;

/**
 * PostgreSQL adapter
 *
 * Possible approach to load datas
 * select row_to_json(t)
 * from (
 *   select id, text from words
 * ) t
 *
 */
class PostgreSql extends \chaos\source\database\Database
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
            return extension_loaded('pdo_pgsql');
        }
        $features = [
            'arrays' => true,
            'transactions' => true,
            'booleans' => true,
            'schema' => true,
            'sources' => true
        ];
        return isset($features[$feature]) ? $features[$feature] : null;
    }

    /**
     * Constructs the PostgreSQL adapter and sets the default port to 5432.
     *
     * @param array $config Configuration options for this class. Available options
     *                      defined by this class:
     *                      - `'host'`    : _string_ The IP or machine name where PostgreSQL is running,
     *                                      followed by a colon, followed by a port number or socket.
     *                                      Defaults to `'localhost:5432'`.
     *                      - `'schema'`  : _string_ The name of the database schema to use. Defaults to 'public'
     *                      - `'timezone'`: _stirng_ The database timezone. Defaults to `null`.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'host' => 'localhost:5432',
            'schema' => 'public',
            'timezone' => null,
            'classes' => [
                'dialect' => 'sql\dialect\PostgreSql'
            ],
            'handlers' => [
                'cast' => [
                    'boolean' => function($value, $options = []) {
                        return $value === 't';
                    },
                ],
                'datasource' => [
                    'boolean' => function($value, $options = []) {
                        return $value ? 'true' : 'false';
                    },
                    'array' => function($data) {
                        $data = (array) $data;
                        $result = [];
                        foreach ($data as $value) {
                            if (is_array($value)) {
                                $result[] = $this->_handlers['datasource']['array']($value);
                            } else {
                                $value = str_replace('"', '\\"', $value);
                                if (!is_numeric($value)) {
                                    $value = '"' . $value . '"';
                                }
                                $result[] = $value;
                            }
                        }
                        return '{' . join(",", $result) . '}';
                    }
                ]
            ]
        ];

        $config = Set::merge($defaults, $config);
        parent::__construct($config + $defaults);
        $this->formatter('datasource', 'array', $this->_handlers['datasource']['array']);
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
            list($host, $port) = explode(':', $host) + [1 => "5432"];
            $dsn = "pgsql:host=%s;port=%s;dbname=%s";
            $this->_config['dsn'] = sprintf($dsn, $host, $port, $this->_config['database']);
        }

        if (!parent::connect()) {
            return false;
        }

        if ($this->_config['schema']) {
            $this->searchPath($this->_config['schema']);
        }

        if ($this->_config['timezone']) {
            $this->timezone($this->_config['timezone']);
        }
        return true;
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources.
     */
    public function sources() {
        $select = $this->dialect()->statement('select');
        $select->fields('table_name')
            ->from(['information_schema' => ['tables']])
            ->where([
                'table_type'   => 'BASE TABLE',
                'table_schema' => $this->_config['schema']
            ]);
        return $this->_sources($select);
    }

    /**
     * Gets the column schema for a given PostgreSQL table.
     *
     * @param  mixed  $name   Specifies the table name for which the schema should be returned.
     * @param  array  $fields Any schema data pre-defined by the model.
     * @param  array  $meta
     * @return object         Returns a shema definition.
     */
    public function describe($name,  $fields = [], $meta = []) {
        $schema = $this->_classes['schema'];

        if (func_num_args() === 1) {

            $select = $this->dialect()->statement('select');
            $select->fields([
                'column_name' => 'Field',
                'data_type'   => 'Type',
                'is_nullable' => 'Null',
                'column_default' => 'Default',
                'character_maximum_length' => 'CharLength'
            ])
            ->from(['information_schema' => ['columns']])
            ->where([
               'table_name'   => $name,
               'table_schema' => $this->_config['schema']
            ]);

            $columns = $this->query($select->toString());

            foreach ($columns as $column) {
                $field = $this->_column($column['Type']);
                $default = $column['Default'];

                if (preg_match("/^'(.*)'::/", $default, $match)) {
                    $default = $match[1];
                } elseif ($default === 'true') {
                    $default = true;
                } elseif ($default === 'false') {
                    $default = false;
                } else {
                    $default = null;
                }
                $fields[$column['Field']] = $field + [
                    'null'     => ($column['Null'] === 'YES' ? true : false),
                    'default'  => $default
                ];
                if ($fields[$column['Field']]['type'] === 'string') {
                    $fields[$column['Field']]['length'] = $column['CharLength'];
                }
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
     * Gets or sets the search path for the connection
     *
     * @param  $searchPath
     * @return mixed       If setting the searchPath; returns ture on success, else false
     *                     When getting, returns the searchPath
     */
    public function searchPath($searchPath)
    {
        if (empty($searchPath)) {
            $query = $this->_pdo->query('SHOW search_path');
            $searchPath = $query->fetchColumn(1);
            return explode(",", $searchPath);
        }
        try{
            $this->_pdo->exec("SET search_path TO ${searchPath}");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Returns the last insert id from the database.
     *
     * @return mixed Returns the last insert id.
     */
    public function lastInsertId($sequence = null)
    {
        $id = $this->_pdo->lastInsertId($sequence);
        return ($id && $id !== '0') ? $id : null;
    }

    /**
     * Gets or sets the time zone for the connection
     *
     * @param  $timezone
     * @return mixed     If setting the time zone; returns true on success, else false
     *                   When getting, returns the time zone
     */
    public function timezone($timezone = null)
    {
        if (empty($timezone)) {
            $query = $this->_pdo->query('SHOW TIME ZONE');
            return $query->fetchColumn();
        }
        try {
            $this->_pdo->exec("SET TIME ZONE '{$timezone}'");
            return true;
        } catch (PDOException $e) {
            return false;
        }
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
        $encodingMap = ['UTF-8' => 'UTF8'];

        if (empty($encoding)) {
            $query = $this->_pdo->query("SHOW client_encoding");
            $encoding = $query->fetchColumn();
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
