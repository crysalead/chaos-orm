<?php
namespace chaos\source\database\sql;

use set\Set;

/**
 * Ansi SQL dialect
 */
class Sql
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * Pointer to the database adapter.
     *
     * @var object
     */
    protected $_adapter = null;

    /**
     * Quoting identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

    /**
     * Column type definitions.
     *
     * @var array
     */
    protected $_columns = [];

    /**
     * List of SQL operators, paired with handling options.
     *
     * @var array
     */
    protected $_operators = [
        '='            => ['null' => ':is'],
        '<=>'          => [],
        '<'            => [],
        '>'            => [],
        '<='           => [],
        '>='           => [],
        '!='           => ['null' => ':is not'],
        '<>'           => [],
        '-'            => [],
        '+'            => [],
        '*'            => [],
        '/'            => [],
        '%'            => [],
        '>>'           => [],
        '<<'           => [],
        ':='           => [],
        '&'            => [],
        '|'            => [],
        ':mod'         => [],
        ':div'         => [],
        ':like'        => [],
        ':not like'    => [],
        ':is'          => [],
        ':is not'      => [],
        '~'            => ['type' => 'prefix'],
        ':between'     => ['type' => 'between'],
        ':not between' => ['type' => 'between'],
        ':in'          => ['type' => 'list'],
        ':not in'      => ['type' => 'list'],
        ':exists'      => ['type' => 'list'],
        ':not exists'  => ['type' => 'list'],
        ':all'         => ['type' => 'list'],
        ':any'         => ['type' => 'list'],
        ':some'        => ['type' => 'list'],
        // logical operators
        ':not'         => ['type' => 'prefix'],
        ':and'         => [],
        ':or'          => [],
        ':xor'         => []
    ];

    /**
     * Operator builders
     *
     * @var array
     */
    protected $_builders = [];

    /**
     * List of formatter operators
     *
     * @var array
     */
    protected $_formatters = [
        ':key'         => [],
        ':value'       => [],
        ':raw'         => []
    ];

    /**
     * Type conversion definitions
     *
     * @var array
     */
    protected $_types = [];

    /**
     * Date format
     *
     * @var string
     */
    protected $_dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param array $config The config array
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes' => [
                'create'    => 'chaos\source\database\sql\statement\Create',
                'select'    => 'chaos\source\database\sql\statement\Select',
                'insert'    => 'chaos\source\database\sql\statement\Insert',
                'update'    => 'chaos\source\database\sql\statement\Update',
                'delete'    => 'chaos\source\database\sql\statement\Delete',
                'drop'      => 'chaos\source\database\sql\statement\Drop'
            ],
            'adapter' => null,
            'columns' => [],
            'operators' => [],
            'builders' => [
                'prefix' => function ($operator, $parts) {
                    $key = array_shift($parts);
                    return "{$key} {$operator} " . reset($parts);
                },
                'list' => function ($operator, $parts) {
                    $key = array_shift($parts);
                    return "{$key} {$operator} (" . join(", ", $parts) . ')';
                },
                'between' => function ($operator, $parts) {
                    $key = array_shift($parts);
                    return "{$key} {$operator} " . reset($parts) . ' AND ' . end($parts);
                },
                'set' => function ($operator, $parts) {
                    return join(" {$operator} ", $parts);
                }
            ],
            'types' => [
                'string'  => function($value, $params = []) { return (string) $value; },
                'integer' => function($value, $params = []) { return (int) $value; },
                'float'   => function($value, $params = []) { return (float) $value; },
                'decimal' => function($value, $params = []) {
                    $params += ['length' => 2];
                    return number_format($number, $params['length']);
                },
                'uuid'    => function($value, $params = []) {
                    $uuid = (string) $value;
                    if (strlen($uuid) != 36) {
                        throw new Exception("Invalid UUID value: `{$uuid}`.");
                    }
                    return $value;
                },
                'boolean' => [
                    'core' => function($value, $params = []) { return !!$value; },
                    'db' => function($value, $params = []) { return $value ? 1 : 0; }
                ]
            ],
            'dateFormat' => 'Y-m-d H:i:s'
        ];
        $defaults['types'] += [
            'text' => $defaults['types']['string'],
            'biginteger' => $defaults['types']['integer'],
            'double' => $defaults['types']['float']
        ];

        $dateToCore = function($value, $params = []) {
            if (is_numeric($value)) {
                return new DateTime('@' . $value);
            }
            return DateTime::createFromFormat($this->_dateFormat, $value);
        };

        $dateToDb = function($value, $params = []) {
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
        };

        foreach (['datetime', 'timestamp', 'date', 'time'] as $type) {
            $defaults[$type]['core'] = $dateToCore;
            $defaults[$type]['db'] = $dateToDb;
        }

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_adapter = $config['adapter'];
        $this->_types = $config['types'];
        $this->_builders = $config['builders'];
        $this->_dateFormat = $config['dateFormat'];
        $this->_columns = $config['columns'] + $this->_columns;
        $this->_operators = $config['operators'] + $this->_operators;
    }

    protected function cast($mode, $type, $value, $params = [])
    {
        if (!isset($this->_types[$type])) {
            throw new SqlException("Invalid type : `{$type}`.");
        }
        if (!isset($this->_column[$type])) {
            throw new SqlException("Invalid column type : `{$type}`.");
        }

        $params += $this->_column[$type];
        $func = $this->_types[$type];

        if ($func instanceof Closure) {
            return $func($value, $params);
        }

        if (!isset($func[$mode])) {
            throw new SqlException("Invalid mode `{$mode}` for type `{$type}`.");
        }

        return $func[$mode]($value, $params);
    }

    /**
     * SQL Statement factory
     *
     * @param string $name The name of the statement to instantiate.
     * @param
     */
    public function statement($name, $config = [])
    {
        $defaults = ['adapter' => $this];
        $config += $defaults;

        if (!isset($this->_classes[$name])) {
            throw new SqlException("Unsupported statement `$name`");
        }
        $statement = $this->_classes[$name];
        return new $statement($config);
    }

    /**
     * SELECT statement
     */
    public function select()
    {
        return 'SELECT';
    }

    /**
     * FROM statement
     */
    public function from($sources = [])
    {
        if (!$sources) {
            throw new SqlException("A `FROM` statement require at least one table.");
        }
        return 'FROM ' . $this->tables($sources);
    }

    /**
     * JOIN statement
     */
    public function joins($join = [])
    {
        $defaults = ['type' => 'LEFT'];
        //$join += $defaults;

        return '';
    }

    /**
     * GROUP statement
     */
    public function group($value)
    {
        return $this->_sort($value, 'GROUP BY');
    }

    /**
     * ORDER statement
     */
    public function order($value)
    {
        return $this->_sort($value, 'ORDER BY');
    }

    /**
     * Helper method
     *
     * @param  mixed  $field  The field.
     * @param  string $clause The clause name.
     * @return string         Formatted clause.
     */
    protected function _sort($field, $clause = 'ORDER BY', $direction = true)
    {
        $direction = $direction ? ' ASC' : '';

        if (is_string($field)) {
            if (preg_match('/^(.*?)\s+((?:A|DE)SC)$/i', $field, $match)) {
                $field = $match[1];
                $direction = $match[2];
            }
            $field = [$field => $direction];
        }

        if (!is_array($field) || empty($field)) {
            return;
        }
        $result = [];

        foreach ($field as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = $direction;
            }
            $dir = preg_match('/^(asc|desc)$/i', $dir) ? " {$dir}" : $direction;

            $column = $this->name($column);
            $result[] = "{$column}{$dir}";
        }
        $fields = join(', ', $result);
        return "{$clause} {$fields}";
    }

    /**
     * LIMIT statement
     */
    public function limit($offset = null, $limit = null)
    {
        return '';
    }

    /**
     * Generate a list of field identifiers
     */
    public function fields($fields = null)
    {
        if (!$fields) {
            return '*';
        }
        return join(', ', $this->_names(is_array($fields) ? $fields : func_get_args(), true));
    }

    /**
     * Generate a list of table identifers
     */
    public function tables($tables = [])
    {
        return join(', ', $this->_names(is_array($tables) ? $tables : func_get_args(), false));
    }

    /**
     * Generate a list of identifers
     */
    public function _names($names, $star = false, $prefix = null)
    {
        $sql = [];
        foreach ($names as $key => $value) {
            if (!is_array($value)) {
                $value = ($value === '*' && $star) ? '*' : $this->escape($value);
                $result = !is_numeric($key) ? $this->escape($key) . ' AS ' . $value : $value;
                $sql[] = $prefix ? "{$prefix}.{$result}" : $result;
                continue;
            }
            $op = key($value);
            if (isset($op[0]) && $op[0] === ':') {
                $sql[] = $this->conditions($value);
            } else {
                $prefix = $this->escape($key);
                $sql = array_merge($sql, $this->_names($value, $star, $prefix));
            }
        }
        return $sql;
    }

    /**
     * Returns a string of formatted conditions to be inserted into the query statement. If the
     * query conditions are defined as an array, key pairs are converted to SQL strings.
     *
     * Conversion rules are as follows:
     *
     * - If `$key` is numeric and `$value` is a string, `$value` is treated as a literal SQL
     *   fragment and returned.
     *
     * @param string|array $conditions The conditions for this query.
     * @param array        $options    - `prepend` _boolean_: Whether the return string should be
     *                                 prepended with the `WHERE` keyword.
     * @return string                  Returns the `WHERE` clause of an SQL query.
     */
    public function where($conditions, $options = [])
    {
        $defaults = ['prepend' => 'WHERE'];
        $options += $defaults;
        return $this->conditions($conditions, $options);
    }

    /**
     * Returns a string of formatted havings to be inserted into the query statement. If the
     * query havings are defined as an array, key pairs are converted to SQL strings.
     *
     * Conversion rules are as follows:
     *
     * - If `$key` is numeric and `$value` is a string, `$value` is treated as a literal SQL
     *   fragment and returned.
     *
     * @param string|array $conditions The havings for this query.
     * @param array        $options    - `prepend` _boolean_: Whether the return string should be
     *                                 prepended with the `HAVING` keyword.
     * @return string                  Returns the `HAVING` clause of an SQL query.
     */
    public function having($conditions, $options = [])
    {
        $defaults = ['prepend' => 'HAVING'];
        $options += $defaults;
        return $this->conditions($conditions, $options);
    }

    /**
     * Returns a string of formatted conditions to be inserted into the query statement. If the
     * query conditions are defined as an array, key pairs are converted to SQL strings.
     *
     * Conversion rules are as follows:
     *
     * - If `$key` is numeric and `$value` is a string, `$value` is treated as a literal SQL
     *   fragment and returned.
     *
     * @param  string|array $conditions The conditions for this query.
     * @param  array        $options    - `prepend` mixed: The string to prepend or false for
     *                                  no prepending.
     * @return string                   Returns an SQL conditions clause.
     */
    public function conditions($conditions, $options = [])
    {
        if (!$conditions) {
            return '';
        }
        $defaults = ['prepend' => false, 'operator' => ':and'];
        $options += $defaults;

        if (!is_numeric(key($conditions))) {
            $conditions = [$conditions];
        }

        $result = $this->_operator(strtolower($options['operator']), $conditions);
        return ($options['prepend'] && $result) ? "{$options['prepend']} {$result}" : $result;
    }

    /**
     * Build a SQL operator statement.
     *
     * @param  string $operator   The operator.
     * @param  mixed  $conditions The data for the operator.
     * @return string              Returns a SQL string.
     */
    protected function _operator($operator, $conditions)
    {
        $config = $this->_operators[$operator];
        if (!isset($this->_operators[$operator])) {
            throw new SqlException("Unsupported operator `{$operator}`.");
        }

        $parts = $this->_conditions($conditions);
        $operator = (is_array($parts) && next($parts) === null && isset($config['null'])) ? $config['null'] : $operator;
        $operator = $operator[0] === ':' ? strtoupper(substr($operator, 1)) : $operator;

        if (isset($config['type'])) {
            if (!isset($this->_builders[$config['type']])) {
                throw new SqlException("Unsupported builder `{$config['type']}`.");
            }
            $builder = $this->_builders[$config['type']];
            return $builder($operator, $parts);
        }
        return join(" {$operator} ", $parts);
    }

    public function _conditions($conditions)
    {
        $parts = [];
        foreach ($conditions as $key => $value) {
            $key = strtolower($key);
            if (isset($this->_formatters[$key])) {
                $parts[] = $this->format($key, $value);
            } elseif (isset($this->_operators[$key])) {
                $parts[] = $this->_operator($key, $value);
            } elseif (is_numeric($key)) {
                if (is_array($value)) {
                    $parts = array_merge($parts, $this->_conditions($value));
                } else {
                    $parts[] = $value;
                }
            } else {
                $parts[] = $this->_key($key, $value);

            }
        }
        return $parts;
    }

    /**
     * Build a <key> <operator> <value> SQL statement.
     *
     * @param  string $key    The field name.
     * @param  mixed  $value  The data value.
     * @return string         Returns a SQL string.
     */
    protected function _key($key, $value)
    {
        if (is_array($value)) {
            $operator = strtolower(key($value));
            if (isset($this->_operators[$operator])) {
                $conditions = current($value);
                if (!is_array($conditions)) {
                    $conditions = [$conditions];
                }
                array_unshift($conditions, [':key' => $key]);
                return $this->_operator($operator, $conditions);
            }
        }
        return "{$this->escape($key)} = {$this->value($value)}";
    }

    /**
     * SQL formatter.
     *
     * @param  string $operator The format operator.
     * @param  mixed  $value    The value to format.
     * @return string           Returns a SQL string.
     */
    protected function format($operator, $value)
    {
        switch ($operator) {
            case ':key':
                return $this->escape($value);
            break;
            case ':value':
                return $this->value($value);
            break;
            case ':raw':
                return (string) $value;
            break;
        }
        return $this->value($value);
    }

    /**
     * Escapes a column/table/schema with dotted syntax support.
     *
     * @param  string $name Identifier name.
     * @return string
     */
    public function escape($name)
    {
        list($one, $two) = $this->undot($name);
        return $one ? $this->_escape($one) . '.' . $this->_escape($two) : $this->_escape($name);
    }

    /**
     * Escapes a column/table/schema name.
     *
     * @param  string $name Identifier name.
     * @return string
     */
    public function _escape($name)
    {
        if (is_string($name) && preg_match('/^[a-z0-9_-]+$/i', $name)) {
            return $this->_escape . $name . $this->_escape;
        }
        return $name;
    }

    /**
     * Split dotted syntax into distinct name.
     *
     * @param  string $field A dotted identifier.
     * @return array
     */
    public function undot($field)
    {
        if (is_string($field) && preg_match('/^[a-z0-9_-]+\.([a-z 0-9_-]+|\*)$/i', $field)) {
            return explode('.', $field, 2);
        }
        return [null, $field];
    }

    /**
     * Quote a string.
     *
     * @param  string $string The string to quote.
     * @return string
     */
    public function quote($string)
    {
        if ($this->_adapter) {
            return $this->_adapter->quote($string);
        }
        $replacements = array(
            "\x00"=>'\x00',
            "\n"=>'\n',
            "\r"=>'\r',
            "\\"=>'\\\\',
            "'"=>"\'",
            "\x1a"=>'\x1a'
        );
        return "'" . strtr(addcslashes($string, '%_'), $replacements) . "'";
    }

    /**
     * Converts a given value into the proper type based on a given schema definition.
     *
     * @param mixed $value The value to be converted. Arrays will be recursively converted.
     * @return mixed value with converted type
     */
    public function value($value)
    {
        switch (true) {
            case is_null($value):
                return 'NULL';
            case is_bool($value):
                return $value ? 'TRUE' : 'FALSE';
            case is_string($value):
                return $this->quote($value);
            case is_array($value):
                return 'ARRAY[' . join(', ', $value) . ']';
        }
        return (string) $value;
    }

    /**
     * Generate a database-native column schema string
     *
     * @param  array  $column A field array structured like the following:
     *                        `['name' => 'value', 'type' => 'value' [, options]]`, where options
     *                        can be `'default'`, `'null'`, `'length'` or `'precision'`.
     * @return string         A SQL string formated column.
     */
    public function column($field)
    {
        if (!isset($field['type'])) {
            $field['type'] = 'string';
        }

        if (!isset($field['name'])) {
            throw new SqlException("Column name not defined.");
        }

        if (!isset($this->_columns[$field['type']])) {
            throw new SqlException("Column type `{$field['type']}` does not exist.");
        }

        $field += $this->_columns[$field['type']];

        $field += [
            'name' => null,
            'type' => null,
            'length' => null,
            'precision' => null,
            'default' => null,
            'null' => null
        ];

        $isNumeric = preg_match('/^(integer|float|boolean)$/', $field['type']);
        if ($isNumeric && $field['default'] === '') {
            $field['default'] = null;
        }
        $field['use'] = strtolower($field['use']);
        return $this->_column($field);
    }

    /**
     * Helper for building columns metas
     *
     * @param  array  $metas  The array of column metas.
     * @param  array  $names  If `$names` is not `null` only build meta present in `$names`
     * @param  string $joiner The join character
     * @return string         The SQL constraints
     */
    public function metas($type, $metas, $names = null, $joiner = ' ')
    {
        $result = '';
        $names = $names ? (array) $names : array_keys($metas);
        foreach ($names as $name) {
            $value = isset($metas[$name]) ? $metas[$name] : null;
            if ($value && $meta = $this->meta($type, $name, $value)) {
                $result .= $joiner . $meta;
            }
        }
        return $result;
    }

    /**
     * Build a SQL column/table meta
     *
     * @param  string $type  The type of the meta to build (possible values: 'table' or 'column')
     * @param  string $name  The name of the meta to build
     * @param  mixed  $value The value used for building the meta
     * @return string        The SQL meta string
     */
    public function meta($type, $name, $value)
    {
        $meta = isset($this->_metas[$type][$name]) ? $this->_metas[$type][$name] : null;
        if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
            return;
        }
        $meta += ['keyword' => '', 'escape' => false, 'join' => ' '];
        extract($meta);
        if ($escape === true) {
            $value = $this->value($value, ['type' => 'string']);
        }
        $result = $keyword . $join . $value;
        return $result !== ' ' ? $result : '';
    }

    /**
     * Helper for building columns constraints
     *
     * @param  array  $constraints The array of constraints
     * @param  string $joiner      The join character
     * @return string              The SQL constraints
     */
    public function constraints($constraints, $joiner = ' ', $primary = false)
    {
        $result = '';
        foreach ($constraints as $constraint) {
            if (!isset($constraint['type'])) {
                continue;
            }
            $name = $constraint['type'];
            if ($meta = $this->constraint($name, $constraint, $schema)) {
                $result .= $joiner . $meta;
            }
            if ($name === 'primary') {
                $primary = false;
            }
        }
        if ($primary) {
            $result .= $joiner . $this->constraint('primary', ['column' => $primary]);
        }
        return $result;
    }

    /**
     * Build a SQL column constraint
     *
     * @param  string $name  The name of the meta to build.
     * @param  mixed  $value The value used for building the meta.
     * @return string        The SQL meta string.
     */
    public function constraint($name, $value)
    {
        $value += ['options' => []];
        $meta = isset($this->_constraints[$name]) ? $this->_constraints[$name] : null;
        $template = isset($meta['template']) ? $meta['template'] : null;
        if (!$template) {
            return;
        }

        $data = [];
        foreach ($value as $name => $value) {
            switch ($name) {
                case 'key':
                case 'index':
                    if (isset($meta[$name])) {
                        $data['index'] = $meta[$name];
                    }
                break;
                case 'to':
                    $data[$name] = $this->escape($value);
                break;
                case 'on':
                    $data[$name] = "ON {$value}";
                break;
                case 'expr':
                    if (is_array($value)) {
                        $data[$name] = $this->conditions($value);
                    } else {
                        $data[$name] = $value;
                    }
                break;
                case 'toColumn':
                case 'column':
                    $data[$name] = join(', ', array_map([$this, 'name'], (array) $value));
                break;
            }
        }

        return trim(String::insert($template, $data, ['clean' => ['method' => 'text']]));
    }
}
