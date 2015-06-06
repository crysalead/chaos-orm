<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * SELECT statement.
 */
class Select extends Statement
{
    /**
     * Subquery alias
     */
    protected $_alias = null;

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
            throw new SourceException("A `FROM` clause requires a non empty table.");
        }
        $this->_parts['from'] += array_merge($this->_parts['from'], is_array($sources) ? $sources : func_get_args());
        return $this;
    }

    /**
     * Adds a join to the query
     *
     * @param  string|array $join A join definition.
     * @return string              Formatted `JOIN` clause.
     */
    public function join($join = null, $on = [], $type = 'LEFT')
    {
        if (!$join) {
            return $this;
        }

        $sql = [strtoupper($type), 'JOIN'];
        $name = $this->sql()->names(is_array($join) ? $join : [$join]);
        $sql[] = reset($name);

        if ($on) {
            $sql[] = 'ON';
            $sql[] = $this->sql()->conditions($on);
        }

        $this->_parts['joins'][] = join(' ', $sql);

        return $this;
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
    public function group($fields = null)
    {
        if (!$fields) {
            return $this;
        }
        $this->_parts['group'][] = $this->_sort($fields, false);
        return $this;
    }

    /**
     * Adds some order by fields to the query
     *
     * @param  string|array $fields The fields.
     * @return object                   Returns `$this`.
     */
    public function order($fields = null)
    {
        if (!$fields) {
            return $this;
        }
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

        $result = [];

        foreach ($fields as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = $direction;
            }
            $dir = preg_match('/^(asc|desc)$/i', $dir) ? " {$dir}" : $direction;

            $column = $this->sql()->escape($column);
            $result[] = "{$column}{$dir}";
        }
        return $fields = join(', ', $result);
    }

    /**
     * Adds a limit statement to the query
     *
     * @param  integer $limit  The limit value.
     * @param  integer $offset The offset value.
     * @return object          Returns `$this`.
     */
    public function limit($limit = 0, $offset = 0)
    {
        if (!$limit) {
            return $this;
        }
        if ($offset) {
            $limit .= " OFFSET {$offset}";
        }
        $this->_parts['limit'] = $limit;
        return $this;
    }

    /**
     * If called with a valid alias, the generated select statement
     * will be generated as a subquery
     *
     * @param  string $alias The alias to use for a subquery.
     * @return object        Returns `$this`.
     */
    public function alias($alias = null)
    {
        if (!func_num_args()) {
            return $this->_alias;
        }
        $this->_alias = $alias;
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
            throw new SourceException("Invalid `SELECT` statement missing `FORM` clause.");
        }

        $query[] = $this->_prefix('FROM', join(', ', $this->sql()->names($this->_parts['from'], false)));
        $query[] = join(' ', $this->_parts['joins']);
        $query[] = $this->_prefix('WHERE', join(' AND ', $this->_parts['where']));
        $query[] = $this->_prefix('GROUP BY', join(', ', $this->_parts['group']));
        $query[] = $this->_prefix('HAVING', join(' AND ', $this->_parts['having']));
        $query[] = $this->_prefix('ORDER BY', join(', ', $this->_parts['order']));
        $query[] = $this->_prefix('LIMIT', $this->_parts['limit']);

        $sql = join(' ', array_filter($query));
        if ($this->_alias) {
            return "({$sql}) AS " . $this->sql()->escape($this->_alias);
        }
        return $sql;
    }

    protected function _prefix($prefix, $sql) {
        return $sql ? "{$prefix} {$sql}": '';
    }
}
