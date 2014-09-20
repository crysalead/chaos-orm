<?php
namespace chaos\source\database;

use chaos\SourceException;

class Schema extends \chaos\model\Schema
{
	/**
     * Create the schema.
     *
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function create()
    {
        if (!isset($this->_source)) {
            throw new SourceException("Missing table name (source) for this schema.");
        }
        $query = $this->connection()->sql()->statement('create table');
        $query->table($this->_source)
            ->columns($schema->fields())
            ->constraints($schema->meta());

        return $this->connection()->execute((string) $query);
    }

    /**
     * Drop the schema
     *
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function drop()
    {
        if (!isset($this->_source)) {
            throw new SourceException("Missing table name (source) for this schema.");
        }
        $query = $this->connection()->sql()->statement('drop table');
        $query->table($this->_source);

        return $this->connection()->execute((string) $query);
    }

}