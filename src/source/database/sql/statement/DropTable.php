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
        'ifExists' => false,
        'table'    => [],
        'cascade'  => false,
        'restrict' => false
    ];

    /**
     * Sets the requirements on the table existence.
     *
     * @param  boolean $ifExists If `false` the table must exists, use `true` for a soft drop.
     * @return object          Returns `$this`.
     */
    public function ifExists($ifExists = true)
    {
        $this->_parts['ifExists'] = $ifExists;
        return $this;
    }

    /**
     * Set the table name to create.
     *
     * @param  string $table The table name.
     * @return object        Returns `$this`.
     */
    public function table($table)
    {
        $tables = is_array($table) ? $table : func_get_args();
        $this->_parts['table'] = $tables;
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
        $this->_parts['cascade'] = $cascade;
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
        $this->_parts['restrict'] = $restrict;
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

        if ($this->_parts['ifExists']) {
            $query[] = 'IF EXISTS';
        }

        if (!$this->_parts['table']) {
            throw new SourceException("Invalid `DROP TABLE` statement no table name defined");
        }

        $tables = array_map([$this->sql(), 'escape'], $this->_parts['table']);
        $query[] = join(', ', $tables);

        if ($this->_parts['cascade']) {
            $query[] = 'CASCADE';
        }

        if ($this->_parts['restrict']) {
            $query[] = 'RESTRICT';
        }

        return join(' ', array_filter($query));
    }
}
