<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * INSERT statement.
 */
class Insert extends \chaos\source\database\sql\Statement
{
    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'flags'     => [],
        'into'      => '',
        'values'    => [],
        'returning' => []
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
        if (!$this->_parts['into']) {
            throw new SourceException("Invalid `INSERT` statement missing table name.");
        }

        $fields = array_keys($this->_parts['values']);
        $values = array_values($this->_parts['values']);

        return 'INSERT' .
            $this->_buildFlags($this->_parts['flags']) .
            $this->_buildClause('INTO', $this->sql()->name($this->_parts['into'], true)) .
            $this->_buildChunk('(' . $this->sql()->names($fields, true) . ')', false) .
            $this->_buildChunk('VALUES (' . join(', ', array_map([$this->sql(), 'value'], $values)) . ')') .
            $this->_buildClause('RETURNING', $this->sql()->names($this->_parts['returning'], false, ''));
    }

}
