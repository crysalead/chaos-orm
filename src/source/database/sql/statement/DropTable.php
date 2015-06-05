<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * SQL CRUD helper
 */
class DropTable extends Statement
{
    /**
     * Pointer to the dialect adapter.
     *
     * @var object
     */
    protected $_sql = null;

    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'exists'   => false,
        'table'    => [],
        'cascade'  => false,
        'restrict' => false
    ];

    /**
     * Set the table name to create.
     *
     * @param  string $table The table name.
     * @return object        Returns `$this`.
     */
    public function table($table)
    {
        $tables = is_array($table) ? $table : func_get_args();
        $this->_parts['table'] = array_map([$this->sql(), 'escape'], $tables);
        return $this;
    }

    /**
     * Sets the requirements on the table existence.
     *
     * @param  boolean $exists If `true` the table must exists, use `false` for a soft drop.
     * @return object          Returns `$this`.
     */
    public function exists($exists = true)
    {
        $this->_parts['exists'] = $exists;
        return $this;
    }

    /**
     * Sets cascading value.
     *
     * @param  boolean $cascade If `true` the related views or objects will be removed.
     * @return object           Returns `$this`.
     */
    public function cascade($cascade = true)
    {
        $this->_parts['cascade'] = $exists;
        return $this;
    }

    /**
     * Sets restricting value.
     *
     * @param  boolean $restrict If `true` the table won't be removed if the related views or objects exists.
     * @return object            Returns `$this`.
     */
    public function restrict($restrict = true)
    {
        $this->_parts['restrict'] = $exists;
        return $this;
    }

    /**
     * Render the SQL statement
     *
     * @return string The generated SQL string.
     */
    public function toString()
    {
        $query = ['DROP TABLE'];

        if (!$this->_parts['exists']) {
            $query[] = 'IF EXISTS';
        }

        if (!$this->_parts['table']) {
            throw new SourceException("Invalid DROM TABLE statement missing at least a table name.");
        }

        $query[] = join(', ', $this->_parts['table']);

        if ($this->_parts['cascade']) {
            $query[] = 'CASCADE';
        }

        if ($this->_parts['restrict']) {
            $query[] = 'RESTRICT';
        }

        return join(' ', array_filter($query));
    }
}
