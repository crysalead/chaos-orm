<?php
namespace chaos\source\database\adapter;

use set\Set;

/**
 * PostgreSQL adapter
 */
class PostgreSql extends \chaos\source\database\Database {

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources.
     */
    public function sources() {
        $select = $this->sql()->statement('select');
        $select->fields('table_name')
            ->from(['information_schema' => ['tables']])
            ->where(['table_type' => 'BASE TABLE']);

        return $this->_sources($select);
    }

}
