<?php
namespace chaos\source\database\adapter;

use set\Set;

/**
 * Sqlite3 adapter
 */
class Sqlite3 extends \chaos\source\database\Database {

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
            return extension_loaded('pdo_sqlite');
        }
        $features = [
            'arrays' => false,
            'transactions' => false,
            'booleans' => true,
            'schema' => true,
            'relationships' => true,
            'sources' => true
        ];
        return isset($features[$feature]) ? $features[$feature] : null;
    }

    /**
     * Constructs the Sqlite adapter
     *
     * @param array $config Configuration options for this class. Available options
     *                      defined by this class:
     *                      - `'database'` _string_: The database name. Defaults to `':memory:'`.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'database' => ':memory:'
        ];
        parent::__construct($config + $defaults);
    }

    /**
     * Connects to the database using the options provided to the class constructor.
     *
     * @return boolean Returns `true` if a database connection could be established,
     *                 otherwise `false`.
     */
    public function connect() {
        if (empty($this->_config['dsn'])) {
            $this->_config['dsn'] = sprintf("sqlite:%s", $this->_config['database']);
        }

        return parent::connect();
    }

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    public function sources() {
        $select = $this->sql()->statement('select');
        $select->fields('name')
            ->from('sqlite_master')
            ->where(['type' => 'table']);

        return $this->_sources($select);
    }

    /**
     * Gets the column schema for a given Sqlite3 table.
     *
     * A column type may not always be available, i.e. when during creation of
     * the column no type was declared. Those columns are internally treated
     * by SQLite3 as having a `NONE` affinity. The final schema will contain no
     * information about type and length of such columns (both values will be
     * `null`).
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
    public function describe($entity, $fields = array(), array $meta = array()) {
        $params = compact('entity', 'meta', 'fields');
        $regex = $this->_regex;
        return $this->_filter(__METHOD__, $params, function($self, $params) use ($regex) {
            extract($params);

            if ($fields) {
                return $self->invokeMethod('_instance', array('schema', compact('fields')));
            }
            $name = $self->invokeMethod('_entityName', array($entity, array('quoted' => true)));
            $columns = $self->read("PRAGMA table_info({$name})", array('return' => 'array'));
            $fields = array();
            foreach ($columns as $column) {
                $schema = $self->invokeMethod('_column', array($column['type']));
                $default = $column['dflt_value'];

                if (preg_match("/^'(.*)'/", $default, $match)) {
                    $default = $match[1];
                } elseif ($schema['type'] === 'boolean') {
                    $default = !!$default;
                } else {
                    $default = null;
                }
                $fields[$column['name']] = $schema + array(
                    'null' => $column['notnull'] === '1',
                    'default' => $default
                );
            }
            return $self->invokeMethod('_instance', array('schema', compact('fields')));
        });
    }

    /**
     * Converts database-layer column types to basic types.
     *
     * @param  string $real Real database-layer column type (i.e. "varchar(255)")
     * @return string       Abstract column type (i.e. "string")
     */
    protected function _column($real)
    {
        if (is_array($real)) {
            return $real['type'] . (isset($real['length']) ? "({$real['length']})" : '');
        }

        if (!preg_match("/{$this->_regex['column']}/", $real, $column)) {
            return $real;
        }

        $column = array_intersect_key($column, ['type' => null, 'length' => null]);
        if (isset($column['length']) && $column['length']) {
            $length = explode(',', $column['length']) + [null, null];
            $column['length'] = $length[0] ? intval($length[0]) : null;
            $length[1] ? $column['precision'] = intval($length[1]) : null;
        }

        switch (true) {
            case in_array($column['type'], ['date', 'time', 'datetime', 'timestamp')]:
                return $column;
            case ($column['type'] === 'tinyint' && $column['length'] == '1'):
            case ($column['type'] === 'boolean'):
                return ['type' => 'boolean'];
            break;
            case (strpos($column['type'], 'int') !== false):
                $column['type'] = 'integer';
            break;
            case (strpos($column['type'], 'char') !== false):
                $column['type'] = 'string';
                $column['length'] = 255;
            break;
            case (strpos($column['type'], 'text') !== false):
                $column['type'] = 'text';
            break;
            case (strpos($column['type'], 'blob') !== false || $column['type'] === 'binary'):
                $column['type'] = 'binary';
            break;
            case preg_match('/real|float|double|decimal/', $column['type']):
                $column['type'] = 'float';
            break;
            default:
                $column['type'] = 'text';
            break;
        }
        return $column;
    }

    /**
     * Gets or sets the encoding for the connection.
     *
     * @param  string $encoding If setting the encoding, this is the name of the encoding to set,
     *                          i.e. `'utf8'` or `'UTF-8'` (both formats are valid).
     * @return mixed            If setting the encoding; returns `true` on success, or `false` on
     *                          failure. When getting, returns the encoding as a string.
     */
    public function encoding($encoding = null)
    {
        $encodingMap = ['UTF-8' => 'utf8'];

        if (!$encoding) {
            $query = $this->_connection->query('PRAGMA encoding');
            $encoding = $query->fetchColumn();
            return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
        }
        $encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;

        try {
            $this->_connection->exec("PRAGMA encoding = \"{$encoding}\"");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Execute a given query.
     *
     * @param  string $sql     The sql string to execute
     * @param  array  $options No available options.
     * @return object
     */
    protected function _execute($sql, $options = [])
    {
        $conn = $this->_connection;

        try {
            $resource = $conn->query($sql);
        } catch(PDOException $e) {
            $self->invokeMethod('_error', [$sql]);
        };

        return $self->invokeMethod('_instance', ['result', compact('resource')]);
    }

}
