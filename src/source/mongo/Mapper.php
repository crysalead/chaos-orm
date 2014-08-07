<?php
namespace chaos\source\mongo;

use chaos\SourceException;

/**
 * Map high a level query to a low level query.
 */
class Mapper
{
	/**
     * The normalization definition.
     */
    protected $_normalize = [];

    /**
     * Field mapper
     */
    protected $_mapper = [
        'conditions' => 'query',
        'offset'     => 'skip',
        'order'     => 'sort'
    ];

    /**
     * Map of typical SQL-like operators to their MongoDB equivalents.
     *
     * @var array Keys are SQL-like operators, value is the MongoDB equivalent.
     */
    protected $_operators = [
        '<'   => '$lt',
        '>'   => '$gt',
        '<='  => '$lte',
        '>='  => '$gte',
        '!='  => ['single' => '$ne', 'multiple' => '$nin'],
        '<>'  => ['single' => '$ne', 'multiple' => '$nin'],
        '||'  => '$or',
        '!'   => '$not',
        '&&'  => '$and',
        ':or'  => '$or',
        ':not' => '$not',
        ':and' => '$and',
        ':nor' => '$nor'
    ];

    /**
     * List of comparison operators to use when performing boolean logic in a query.
     *
     * @var array
     */
    protected $_boolean = ['&&', '||', 'and', '$and', 'or', '$or', 'nor', '$nor'];

	/**
     * The constructor
     *
     * @param array $query
     */
    public function __construct($query = []) {
        $this->_query = $query;
        $this->_normalize = [
            'conditions' => 'conditions',
            'match'      => 'conditions',
            'query'      => 'conditions',
            'skip'       => 'offset',
            'group'      => 'group',
            'sort'       => 'order',
            'limit'      => 'limit',
            'pipeline'   => function($data) {
                foreach ($data as $key => $value) {
                    $data[$key] = $this->_value($key, $value);
                }
                return $data;
            }
        ];
    }

    /**
     * Map high a level query to a low level query.
     *
     * @param  array $query The high level query to be parsed.
     * @return array        The low level query.
     */
    public function map($query = []) {
    	$result = [];
        foreach ($query as $name => $value) {
            $result[$this->_field($name)] = $this->_value($name, $value);
        }
        return $result;
    }

    /**
     * Map high a level field name to a low level field name.
     *
     * @param  string $name The high level field name.
     * @return string       The low level field name.
     */
    protected function _field($name) {
    	if (!isset($this->_mapper[$name])) {
            return $name;
        }
        return $this->_mapper[$name];
    }

    /**
     * Map high a level query value to a low level query value.
     *
     * @param  string $name  The field name of the value.
     * @param  array  $value The high level value to be parsed.
     * @return array         The low level query value.
     */
    protected function _value($name, $value) {
        $field = preg_replace('/^\$/', '', $name);
        if (!isset($this->_normalize[$field])) {
            return $value;
        }
        $method = $this->_normalize[$field];
        if (is_callable($method)) {
            return $method($value);
        }
        return $this->$method($value);
    }

    /**
     * Return formatted identifiers for fields.
     *
     * @param  array $fields Fields to be parsed.
     * @return array         Parsed fields array.
     */
    public function fields($fields)
    {
        return $fields ?: [];
    }

    /**
     * Maps incoming conditions with their corresponding MongoDB-native operators.
     *
     * @param  mixed  $conditions Array of conditions.
     * @param  object $schema     The schema to cast conditions value on.
     * @return array              Transformed conditions.
     */
    public function conditions($conditions = [], $schema = null)
    {
        if (!$conditions) {
            return [];
        }
        if ($code = $this->_isMongoCode($conditions)) {
            return $code;
        }
        return $this->_conditions($conditions, $schema);
    }

    /**
     * Protected helper method used to create a MongoCode if applicable.
     *
     * @param  mixed  $conditions Array of conditions.
     * @return array              Transformed conditions or `null` if MongoCode is not applicable.
     */
    protected function _isMongoCode($conditions)
    {
        if (is_string($conditions)) {
            $conditions = new MongoCode($conditions);
        }
        if ($conditions instanceof MongoCode) {
            return ['$where' => $conditions];
        }
    }

    /**
     * Protected helper method used to format conditions.
     *
     * @param  array  $conditions The conditions array to be processed.
     * @param  object $schema     The object containing the schema definition.
     * @return array              Processed query conditions.
     */
    protected function _conditions($conditions, $schema)
    {
        $ops = $this->_operators;
        $castOpts = ['first' => true, 'database' => $this, 'wrap' => false];

        $cast = function($key, $value) use ($schema, $castOpts) {
            return $schema ? $schema->cast(null, $key, $value, $castOpts) : $value;
        };

        foreach ($conditions as $key => $value) {
            if (in_array($key, $this->_boolean)) {
                $operator = isset($ops[$key]) ? $ops[$key] : $key;

                foreach ($value as $i => $compare) {
                    $value[$i] = $this->_conditions($compare,$schema);
                }
                unset($conditions[$key]);
                $conditions[$operator] = $value;
                continue;
            }
            if (is_object($value)) {
                continue;
            }
            if (!is_array($value)) {
                $conditions[$key] = $cast($key, $value);
                continue;
            }
            $current = key($value);

            if (!isset($ops[$current]) && $current[0] !== '$') {
                $conditions[$key] = ['$in' => $cast($key, $value)];
                continue;
            }
            $conditions[$key] = $this->_operators($key, $value, $schema);
        }
        return $conditions;
    }

    /**
     * Protected helper method used to format operators.
     *
     * @param  string $field      The field name.
     * @param  array  $operators  The operators array to be processed.
     * @param  object $schema     The object containing the schema definition.
     * @return array              Processed query conditions.
     */
    protected function _operators($field, $operators, $schema)
    {
        $castOpts = compact('schema');
        $castOpts += ['first' => true, 'database' => $this, 'wrap' => false];

        $cast = function($key, $value) use ($schema, $castOpts) {
            return $schema ? $schema->cast(null, $key, $value, $castOpts) : $value;
        };

        foreach ($operators as $key => $value) {
            if (!isset($this->_operators[$key])) {
                $operators[$key] = $cast($field, $value);
                continue;
            }
            $operator = $this->_operators[$key];

            if (is_array($operator)) {
                $operator = $operator[is_array($value) ? 'multiple' : 'single'];
            }
            if (is_callable($operator)) {
                return $operator($key, $value, $schema);
            }
            unset($operators[$key]);
            $operators[$operator] = $cast($field, $value);
        }
        return $operators;
    }

    /**
     * Formats `group` clauses for MongoDB.
     *
     * @param  string|array $group The group clause.
     * @return array Formatted `group` clause.
     */
    public function group($group)
    {
        if (!$group) {
            return;
        }
        if (is_string($group) && strpos($group, 'function') === 0) {
            return ['$keyf' => new MongoCode($group)];
        }
        $group = (array) $group;

        foreach ($group as $i => $field) {
            if (is_int($i)) {
                $group[$field] = true;
                unset($group[$i]);
            }
        }
        return ['key' => $group];
    }

    /**
     * Return formatted clause for order.
     *
     * @param  mixed $order The `order` clause to be formatted
     * @return mixed        Formatted `order` clause.
     */
    public function order($order)
    {
        if (!$order) {
            return [];
        }
        if (is_string($order)) {
            return [$order => 1];
        }
        if (!is_array($order)) {
            return [];
        }
        foreach ($order as $key => $value) {
            if (!is_string($key)) {
                unset($order[$key]);
                $order[$value] = 1;
                continue;
            }
            if (is_string($value)) {
                $order[$key] = strtolower($value) === 'asc' ? 1 : -1;
            }
        }
        return $order;
    }

    /**
     * Return formatted clause for limit.
     *
     * @param  mixed $limit The `limit` clause to be formatted.
     * @return mixed        Formatted `limit` clause.
     */
    public function limit($limit)
    {
        return $limit ?: 0;
    }
}
