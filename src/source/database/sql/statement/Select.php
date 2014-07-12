<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * SQL CRUD helper
 */
class Select extends Statement
{
    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'fields' => [],
        'from'   => [],
        'joins'  => [],
        'where'  => [],
        'group'  => [],
        'having' => [],
        'order'  => [],
        'limit'  => ''
    ];

    /**
     * Adds some fields to the query
     *
     * @param  string|array $fields The fields.
     * @return string               Formatted fields list.
     */
    public function fields($fields)
    {
        $names = $this->sql()->names(is_array($fields) ? $fields : func_get_args(), true);
        $this->_parts['fields'] = array_merge($this->_parts['fields'], $names);
        return $this;
    }

    /**
     * Adds some tables in the from statement
     *
     * @param  string|array $sources The source tables.
     * @return string                Formatted source table list.
     */
    public function from($sources)
    {
        if (!$sources) {
            throw new SourceException("A `FROM` statement require at least one table.");
        }
        $names = $this->sql()->names(is_array($sources) ? $sources : func_get_args(), false);
        $this->_parts['from'] += array_merge($this->_parts['from'], $names);
        return $this;
    }

    /**
     * Adds a join to the query
     *
     * @param  string|array $joins The joins.
     * @return string              Formatted `JOIN` clause.
     */
    public function join($join)
    {
        $defaults = ['type' => 'LEFT'];
        //$join += $defaults;

        return '';
    }

    /**
     * Adds some where conditions to the query
     *
     * @param  string|array $conditions The conditions for this query.
     * @return object                   Returns `$this`.
     */
    public function where($conditions)
    {
        if ($conditions = $this->sql()->conditions($conditions)) {
            $this->_parts['where'][] = $conditions;
        }
        return $this;
    }

    /**
     * Adds some having conditions to the query
     *
     * @param  string|array $conditions The havings for this query.
     * @return object                   Returns `$this`.
     */
    public function having($conditions)
    {
        if ($conditions = $this->sql()->conditions($conditions)) {
            $this->_parts['having'][] = $conditions;
        }
        return $this;
    }

    /**
     * Adds some group by fields to the query
     *
     * @param  string|array $fields The fields.
     * @return object                   Returns `$this`.
     */
    public function group($fields)
    {
        $this->_parts['group'][] = $this->_sort($fields);
        return $this;
    }

    /**
     * Adds some order by fields to the query
     *
     * @param  string|array $fields The fields.
     * @return object                   Returns `$this`.
     */
    public function order($fields)
    {
        $this->_parts['order'][] = $this->_sort($fields);
        return $this;
    }

    /**
     * Helper method
     *
     * @param  string|array $fields The fields.
     * @return string       Formatted fields.
     */
    protected function _sort($fields, $direction = true)
    {
        $direction = $direction ? ' ASC' : '';

        if (is_string($fields)) {
            if (preg_match('/^(.*?)\s+((?:a|de)sc)$/i', $fields, $match)) {
                $fields = $match[1];
                $direction = $match[2];
            }
            $fields = [$fields => $direction];
        }

        if (!is_array($fields) || !$fields) {
            return '';
        }
        $result = [];

        foreach ($fields as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = $direction;
            }
            $dir = preg_match('/^(asc|desc)$/i', $dir) ? " {$dir}" : $direction;

            $column = $this->name($column);
            $result[] = "{$column}{$dir}";
        }
        return $fields = join(', ', $result);
    }

    /**
     * Adds a limit statement to the query
     *
     * @param  integer $offset The offset value.
     * @param  integer $limit  The limit value.
     * @return object          Returns `$this`.
     */
    public function limit($offset, $limit)
    {
        if (!$limit) {
            $this->_parts['limit'] = '';
            return $this;
        }
        $offset = $offset ?: '';
        $this->_parts['limit'] = "LIMIT {$limit}{$offset}";
        return $this;
    }

    /**
     * Render the SQL statement
     *
     * @return string The generated SQL string.
     * @throws chaos\SourceException
     */
    public function toString()
    {
        $query = ['SELECT'];
        if ($this->_parts['fields']) {
            $query[] = join(', ', $this->_parts['fields']);
        } else {
            $query[] = '*';
        }

        if (!$this->_parts['from']) {
            throw new SourceException("Invalid SELECT statement missing FORM clause.");
        }

        $query[] = $this->_prefix('FROM', join(', ', $this->_parts['from']));
        $query[] = join(' ', $this->_parts['joins']);
        $query[] = $this->_prefix('WHERE', join(' AND ', $this->_parts['where']));
        $query[] = $this->_prefix('GROUP BY', join(', ', $this->_parts['group']));
        $query[] = $this->_prefix('HAVING', join(' AND ', $this->_parts['having']));
        $query[] = $this->_prefix('ORDER BY', join(', ', $this->_parts['order']));
        $query[] = $this->_prefix('LIMIT', $this->_parts['limit']);

        return join(' ', array_filter($query));
    }

    protected function _prefix($prefix, $sql) {
        return $sql ? "{$prefix} {$sql}": '';
    }
}
