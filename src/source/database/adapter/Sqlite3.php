<?php
namespace chaos\source\database\adapter;

use set\Set;

/**
 * Sqlite3 adapter
 */
class Sqlite3 extends \chaos\source\database\Database {

    /**
     * Returns the list of tables in the currently-connected database.
     *
     * @return array Returns an array of sources to which models can connect.
     */
    public function sources() {
        $select = $this->sql()->statement('select');
        $select->fields('name')
            ->from('sqlite_master')
            ->where(['type' => 'table']);

        return $this->_sources($select);
    }

}
