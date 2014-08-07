<?php
namespace chaos\source\database;

use PDO;
use PDOStatement;
use PDOException;

/**
 * This class is a wrapper around the `PDOStatement` returned and can be used to iterate over it.
 *
 * @link http://php.net/manual/de/class.pdostatement.php The PDOStatement class.
 */
class Schema extends \chaos\source\Schema
{
	/**
     * Create the schema.
     *
     * @return boolean
     * @throws chaos\SourceException If no connection is defined or the schema name is missing.
     */
    public function create()
    {
        if (!isset($this->_name)) {
            throw new SourceException("Missing name for this schema.");
        }
        $query = $this->connection()->sql()->statement('create table');
        $query->table($source)
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
    public function drop($method, $params = [])
    {
        if (!isset($this->_name)) {
            throw new SourceException("Missing name for this schema.");
        }
        $query = $this->connection()->sql()->statement('drop table');
        $query->table($source);

        return $this->connection()->execute((string) $query);
    }

}