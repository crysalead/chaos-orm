<?php
namespace chaos\source\database;

use chaos\SourceException;

class Schema extends \chaos\model\Schema
{
	/**
     * Create the schema.
     *
     * @param  array   $options An array of options.
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function create($options = [])
    {
        $defaults = [
            'soft' => true
        ];
        $options += $defaults;

        if (!isset($this->_source)) {
            throw new SourceException("Missing table name (source) for this schema.");
        }
        $query = $this->connection()->sql()->statement('create table');
        $query
            ->notExists($options['soft'])
            ->table($this->_source)
            ->columns($this->fields())
            ->constraints($this->meta('constraints'))
            ->meta($this->meta());

        return $this->connection()->execute((string) $query);
    }

    /**
     * Drop the schema
     *
     * @param  array   $options An array of options.
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function drop($options = [])
    {
        $defaults = [
            'soft'     => true,
            'cascade'  => false,
            'restrict' => false
        ];
        $options += $defaults;

        if (!isset($this->_source)) {
            throw new SourceException("Missing table name (source) for this schema.");
        }
        $query = $this->connection()->sql()->statement('drop table');
        $query
            ->exists($options['soft'])
            ->table($this->_source)
            ->cascade($options['cascade'])
            ->restrict($options['restrict']);

        return $this->connection()->execute((string) $query);
    }
}
