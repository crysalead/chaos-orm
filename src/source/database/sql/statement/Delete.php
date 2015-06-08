<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * `DELETE` statement.
 */
class Delete extends \chaos\source\database\sql\Statement
{
    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'flags'     => [],
        'from'      => [],
        'where'     => [],
        'order'     => [],
        'limit'     => '',
        'returning' => []
    ];

    /**
     * Sets the table name to create.
     *
     * @param  string $from The table name.
     * @return object       Returns `$this`.
     */
    public function from($from)
    {
        $this->_parts['from'] = $from;
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
     * Render the SQL statement
     *
     * @return string The generated SQL string.
     * @throws chaos\SourceException
     */
    public function toString()
    {
        if (!$this->_parts['from']) {
            throw new SourceException("Invalid `DELETE` statement missing table name.");
        }

        return 'DELETE' .
            $this->_buildFlags($this->_parts['flags']) .
            $this->_buildClause('FROM', $this->sql()->names($this->_parts['from'], true)) .
            $this->_buildClause('WHERE', join(' AND ', $this->_parts['where'])) .
            $this->_buildClause('ORDER BY', join(', ', $this->_parts['order'])) .
            $this->_buildClause('LIMIT', $this->_parts['limit']) .
            $this->_buildClause('RETURNING', $this->sql()->names($this->_parts['returning'], false, ''));
    }

}
