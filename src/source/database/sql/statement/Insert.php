<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * INSERT statement.
 */
class Insert extends Statement
{
    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'into'   => '',
        'values' => []
    ];

    /**
     * Sets the `INTO` clause value.
     *
     * @param  string|array $into The table name.
     * @return object              Returns `$this`.
     */
    public function into($into)
    {
        $this->_parts['into'] = $into;
        return $this;
    }

    /**
     * Sets the `INSERT` values.
     *
     * @param  string|array $values The record values to insert.
     * @return object               Returns `$this`.
     */
    public function values($values)
    {
        $this->_parts['values'] = $values;
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
        $query = ['INSERT INTO'];

        if (!$this->_parts['into']) {
            throw new SourceException("Invalid `INSERT` statement missing `INTO` clause.");
        }

        $query[] = $this->sql()->escape($this->_parts['into']);

        $keys = array_map([$this->sql(), 'escape'], array_keys($this->_parts['values']));
        $query[] = '(' . join(', ', $keys) . ')';

        $values = array_map([$this->sql(), 'value'], array_values($this->_parts['values']));
        $query[] = 'VALUES (' . join(', ', $values) . ')';

        $sql = join(' ', array_filter($query));
        return $sql;
    }

}
