<?php
namespace chaos\source\database\sql;

use stdClass;
use set\Set;
use string\String;
use chaos\SourceException;

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
    protected $_types = [];

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
                'create'       => 'chaos\source\database\sql\statement\Create',
                'select'       => 'chaos\source\database\sql\statement\Select',
                'insert'       => 'chaos\source\database\sql\statement\Insert',
                'update'       => 'chaos\source\database\sql\statement\Update',
                'delete'       => 'chaos\source\database\sql\statement\Delete',
                'create table' => 'chaos\source\database\sql\statement\CreateTable',
                'drop table'   => 'chaos\source\database\sql\statement\DropTable'
            ],
            'adapter' => null,
            'types' => [],
            'operators' => [],
            'builders' => $this->_builders(),
            'dateFormat' => 'Y-m-d H:i:s'
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_adapter = $config['adapter'];
        $this->_builders = $config['builders'];
        $this->_dateFormat = $config['dateFormat'];
        $this->_types = $config['types'] + $this->_types;
        $this->_operators = $config['operators'] + $this->_operators;
    }

    /**
     * Return default operator builders
     *
     * @return array
     */
    protected function _builders() {
        return [
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
        ];
    }

    /**
     * Datasource adapter getter/setter
     *
     * @param  object|null $adapter The adapter instance to set to `null` if used as a getter.
     * @return object|null          Returns the adatper or `null` if no adapter was set.
     */
    public function adapter($adapter = null) {
        if ($adapter !== null) {
            $this->_adapter = $adapter;
        }
        return $this->_adapter;
    }

    /**
     * SQL Statement factory
     *
     * @param string $name The name of the statement to instantiate.
     * @param
     */
    public function statement($name, $config = [])
    {
        $defaults = ['sql' => $this];
        $config += $defaults;

        if (!isset($this->_classes[$name])) {
            throw new SourceException("Unsupported statement `$name`");
        }
        $statement = $this->_classes[$name];
        return new $statement($config);
    }

    /**
     * Generate a list of identifers
     */
    public function names($names, $star = false, $prefix = null)
    {
        $sql = [];
        foreach ($names as $key => $value) {
            if (!is_array($value)) {
                $value = ($value === '*' && $star) ? '*' : $this->escape($value);
                if (!is_numeric($key)) {
                    $name = $this->escape($key);
                    $name = $prefix ? "{$prefix}.{$name}" : $name;
                    $sql[$name] = "{$name} AS {$value}";
                } else {
                    $name = $value;
                    $name = $prefix ? "{$prefix}.{$name}" : $name;
                    $sql[$name] = $name;
                }
                continue;
            }
            $op = key($value);
            if (isset($op[0]) && $op[0] === ':') {
                $sql[] = $this->conditions($value);
            } else {
                $prefix = $this->escape($key);
                $sql = array_merge($sql, $this->names($value, $star, $prefix));
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
        $defaults = ['prepend' => false, 'operator' => ':and', 'schemas' => []];
        $options += $defaults;

        if (!is_numeric(key($conditions))) {
            $conditions = [$conditions];
        }

        $states = [
            'schemas' => $options['schemas'],
            'schema' => null,
            'key' => null,
        ];

        $result = $this->_operator(strtolower($options['operator']), $conditions, $states);
        return ($options['prepend'] && $result) ? "{$options['prepend']} {$result}" : $result;
    }

    /**
     * Build a SQL operator statement.
     *
     * @param  string $operator   The operator.
     * @param  mixed  $conditions The data for the operator.
     * @return string              Returns a SQL string.
     */
    protected function _operator($operator, $conditions, &$states)
    {
        $config = $this->_operators[$operator];
        if (!isset($this->_operators[$operator])) {
            throw new SourceException("Unsupported operator `{$operator}`.");
        }

        $parts = $this->_conditions($conditions, $states);
        $operator = (is_array($parts) && next($parts) === null && isset($config['null'])) ? $config['null'] : $operator;
        $operator = $operator[0] === ':' ? strtoupper(substr($operator, 1)) : $operator;

        if (isset($config['type'])) {
            if (!isset($this->_builders[$config['type']])) {
                throw new SourceException("Unsupported builder `{$config['type']}`.");
            }
            $builder = $this->_builders[$config['type']];
            return $builder($operator, $parts);
        }
        return join(" {$operator} ", $parts);
    }

    /**
     * Build a formated array of SQL statement.
     *
     * @param  string $key    The field name.
     * @param  mixed  $value  The data value.
     * @return array          Returns a array of SQL string.
     */
    public function _conditions($conditions, &$states)
    {
        $parts = [];
        foreach ($conditions as $key => $value) {
            $operator = strtolower($key);
            if (isset($this->_formatters[$operator])) {
                $parts[] = $this->format($operator, $value, $states['key'], $states['schema']);
            } elseif (isset($this->_operators[$operator])) {
                $parts[] = $this->_operator($operator, $value, $states);
            } elseif (is_numeric($key)) {
                if (is_array($value)) {
                    $parts = array_merge($parts, $this->_conditions($value, $states));
                } else {
                    $parts[] = $this->value($value, $states['key'], $states['schema']);
                }
            } else {
                $parts[] = $this->_key($key, $value, $states);
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
    protected function _key($key, $value, &$states)
    {
        $escaped = $this->escape($key, $alias, $field);
        $schema = isset($states['schemas'][$alias]) ? $states['schemas'][$alias] : null;
        $states['key'] = $field;
        $states['schema'] = $schema;

        if (is_array($value)) {
            $operator = strtolower(key($value));
            if (isset($this->_operators[$operator])) {
                $conditions = current($value);
                if (!is_array($conditions)) {
                    $conditions = [$conditions];
                }
                array_unshift($conditions, [':key' => $key]);
                return $this->_operator($operator, $conditions, $states);
            }
        }
        return "{$escaped} = {$this->value($value, $key, $schema)}";
    }

    /**
     * SQL formatter.
     *
     * @param  string $operator The format operator.
     * @param  mixed  $value    The value to format.
     * @return string           Returns a SQL string.
     */
    protected function format($operator, $value, $key = null, $schema = null)
    {
        switch ($operator) {
            case ':key':
                return $this->escape($value);
            break;
            case ':value':
                return $this->value($value, $key, $schema);
            break;
            case ':raw':
                return (string) $value;
            break;
        }
        return $this->value($value, $key, $schema);
    }

    /**
     * Escapes a column/table/schema with dotted syntax support.
     *
     * @param  string $name  Identifier name.
     * @param  string $alias The filled alias name if present.
     * @return string        The escaped identifien.
     */
    public function escape($name, &$alias = null, &$field = null)
    {
        list($alias, $field) = $this->undot($name);
        return $alias ? $this->_escape($alias) . '.' . $this->_escape($field) : $this->_escape($name);
    }

    /**
     * Escapes a column/table/schema name.
     *
     * @param  string $name Identifier name.
     * @return string
     */
    protected function _escape($name)
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
    public function value($value, $key = null, $schema = null)
    {
        if ($schema) {
            $value = $schema->export($key, $value);
        }
        switch (true) {
            case is_null($value):
                return 'NULL';
            case is_bool($value):
                return $value ? 'TRUE' : 'FALSE';
            case is_string($value):
                return $this->quote($value);
            case is_array($value):
                return '{' . join(', ', $value) . '}';
            case $value instanceof stdClass:
                return $value->scalar;
        }
        return (string) $value;
    }


    // protected function cast($mode, $type, $value, $params = [])
    // {
    //     if (!isset($this->_handlers[$mode])) {
    //         throw new SourceException("Invalid mode `{$mode}`.");
    //     }
    //     $handlers = $this->_handlers[$mode];

    //     if (!isset($this->_column[$type])) {
    //         throw new SourceException("Invalid column type : `{$type}`.");
    //     }

    //     $params += $this->_column[$type];
    //     $func = $this->_handlers[$type];

    //     if ($func instanceof Closure) {
    //         return $func($value, $params);
    //     }

    //     if (!isset($func[$mode])) {
    //         throw new SourceException("Invalid mode `{$mode}` for type `{$type}`.");
    //     }

    //     return $func[$mode]($value, $params);
    // }

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
            throw new SourceException("Column name not defined.");
        }

        $field += $this->adapter()->type($field['type']);

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
     * @return string         The SQL metas
     */
    public function metas($type, $metas, $names = null)
    {
        $result = [];
        $names = $names ? (array) $names : array_keys($metas);
        foreach ($names as $name) {
            $value = isset($metas[$name]) ? $metas[$name] : null;
            if ($value && $meta = $this->meta($type, $name, $value)) {
                $result[] = $meta;
            }
        }
        return join(' ', $result);
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
     * Build a SQL column constraint
     *
     * @param  string $name  The name of the meta to build.
     * @param  mixed  $value The value used for building the meta.
     * @return string        The SQL meta string.
     */
    public function constraint($name, $value, $schemas = [])
    {
        $value += ['options' => []];
        $meta = isset($this->_constraints[$name]) ? $this->_constraints[$name] : null;
        if (!($template = isset($meta['template']) ? $meta['template'] : null)) {
            throw new SourceException("Invalid constraint template `'{$name}'`.", 1);
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
                case 'constraint':
                    $data[$name] = "CONSTRAINT " . $this->escape($value);
                break;
                case 'expr':
                    if (is_array($value)) {
                        $data[$name] = $this->conditions($value, compact('schemas'));
                    } else {
                        $data[$name] = $value;
                    }
                break;
                case 'column':
                case 'primaryKey';
                case 'foreignKey';
                    $data[$name] = join(', ', array_map([$this, 'escape'], (array) $value));
                break;
            }
        }

        return trim(String::insert($template, $data, ['clean' => ['method' => 'text']]));
    }
}
