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
class PostgreSql extends \chaos\source\database\Database {

    /**
     * PostgreSql types matching
     *
     * @var array
     */
    protected $_defaults = [
        'bool'          => 'boolean',
        'int2'          => 'integer',
        'int4'          => 'integer',
        'int8'          => 'integer',
        'float4'        => 'float',
        'float8'        => 'float',
        'bytea'         => 'binary',
        'text'          => 'string',
        'macaddr'       => 'string',
        'inet'          => 'string',
        'cidr'          => 'string',
        'string'        => 'string',
        'date'          => 'date',
        'time'          => 'time',
        'timestamp'     => 'datetime',
        'timestamptz'   => 'datetime',
        'lseg'          => 'string',
        'path'          => 'string',
        'box'           => 'string',
        'polygon'       => 'string',
        'line'          => 'string',
        'circle'        => 'string',
        'bit'           => 'string',
        'varbit'        => 'string',
        'decimal'       => 'string',
        'uuid'          => 'string',
        'tsvector'      => 'string',
        'tsquery'       => 'string',
        'txid_snapshot' => 'string',
        'json'          => 'string',
        'xml'           => 'string'
    ];

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
                'sql' => 'chaos\source\database\sql\dialect\PostgreSqlDialect'
            ],
            'handlers' => [
                'importBoolean' => function($value, $params = []) { return $value === 't'; },
                'exportArray' => function($data) {
                    $data = (array) $data;
                    $result = [];
                    foreach ($data as $value) {
                        if (is_array($value)) {
                            $result[] = $this->_handlers['exportArray']($value);
                        } else {
                            $value = str_replace('"', '\\"', $value);
                            if (!is_numeric($value)) {
                                $value = '"' . $value . '"';
                            }
                            $result[] = $value;
                        }
                    }
                    return '{' . join(",", $result) . '}'; // format
                }
            ] + $this->_handlers()
        ];

        $config = Set::merge($defaults, $config);
        parent::__construct($config + $defaults);

        $this->type('id',       ['use' => 'integer']);
        $this->type('serial',   ['use' => 'integer', 'serial' => true]);
        $this->type('string',   ['use' => 'varchar', 'length' => 255]);
        $this->type('text',     ['use' => 'text']);
        $this->type('integer',  ['use' => 'integer']);
        $this->type('boolean',  ['use' => 'boolean']);
        $this->type('float',    ['use' => 'real']);
        $this->type('decimal',  ['use' => 'numeric', 'precision' => 2]);
        $this->type('date',     ['use' => 'date']);
        $this->type('time',     ['use' => 'time']);
        $this->type('datetime', ['use' => 'timestamp']);
        $this->type('binary',   ['use' => 'bytea']);
        $this->type('uuid',     ['use' => 'uuid']);

        $this->format('id',       'intval');
        $this->format('serial',   'intval');
        $this->format('integer',  'intval');
        $this->format('float',    'floatval');
        $this->format('decimal',  'toDecimal');
        $this->format('date',     'importDate',    'exportDate');
        $this->format('datetime', 'importDate',    'exportDate');
        $this->format('boolean',  'importBoolean', 'exportBoolean');
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
        $select = $this->sql()->statement('select');
        $select->fields('table_name')
            ->from(['information_schema' => ['tables']])
            ->where(['table_type' => 'BASE TABLE']);

        return $this->_sources($select);
    }

    /**
     * Gets the column schema for a given PostgreSQL table.
     *
     * @param  mixed $entity Specifies the table name for which the schema should be returned, or
     *                       the class name of the model object requesting the schema, in which case
     *                       the model class will be queried for the correct table name.
     * @param  array $fields Any schema data pre-defined by the model.
     * @param  array $meta
     * @return array         Returns an associative array describing the given table's schema,
     *                       where the array keys are the available fields, and the values are arrays
     *                       describing each field, containing the following keys:
     *                       - `'type'`: _string_ The field type name.
     */
    public function describe($entity, $fields = array(), array $meta = array()) {
        $schema = $this->_config['schema'];
        $params = compact('entity', 'meta', 'fields', 'schema');
        return $this->_filter(__METHOD__, $params, function($self, $params) {
            extract($params);

            if ($fields) {
                return $self->invokeMethod('_instance', array('schema', compact('fields')));
            }
            $name = $self->_pdo->quote($self->invokeMethod('_entityName', array($entity)));
            $schema = $self->_pdo->quote($schema);

            $sql = 'SELECT "column_name" AS "field", "data_type" AS "type", ';
            $sql .= '"is_nullable" AS "null", "column_default" AS "default", ';
            $sql .= '"character_maximum_length" AS "char_length" ';
            $sql .= 'FROM "information_schema"."columns" WHERE "table_name" = ' . $name;
            $sql .= ' AND table_schema = ' . $schema . ' ORDER BY "ordinal_position"';

            $columns = $self->_pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $fields = array();

            foreach ($columns as $column) {
                $schema = $self->invokeMethod('_column', array($column['type']));
                $default = $column['default'];

                if (preg_match("/^'(.*)'::/", $default, $match)) {
                    $default = $match[1];
                } elseif ($default === 'true') {
                    $default = true;
                } elseif ($default === 'false') {
                    $default = false;
                } else {
                    $default = null;
                }
                $fields[$column['field']] = $schema + array(
                    'null'     => ($column['null'] === 'YES' ? true : false),
                    'default'  => $default
                );
                if ($fields[$column['field']]['type'] === 'string') {
                    $fields[$column['field']]['length'] = $column['char_length'];
                }
            }
            return $self->invokeMethod('_instance', array('schema', compact('fields')));
        });
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

        switch (true) {
            case in_array($column['type'], ['date', 'time', 'datetime']):
                return $column;
            case ($column['type'] === 'timestamp'):
                $column['type'] = 'datetime';
            break;
            case ($column['type'] === 'tinyint' && $column['length'] == '1'):
            case ($column['type'] === 'boolean'):
                return ['type' => 'boolean'];
            break;
            case (strpos($column['type'], 'int') !== false):
                $column['type'] = 'integer';
            break;
            case (strpos($column['type'], 'char') !== false || $column['type'] === 'tinytext'):
                $column['type'] = 'string';
            break;
            case (strpos($column['type'], 'text') !== false):
                $column['type'] = 'text';
            break;
            case (strpos($column['type'], 'blob') !== false || $column['type'] === 'binary'):
                $column['type'] = 'binary';
            break;
            case preg_match('/float|double|decimal/', $column['type']):
                $column['type'] = 'float';
            break;
            default:
                $column['type'] = 'text';
            break;
        }
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
