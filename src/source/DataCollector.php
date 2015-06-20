<?php
namespace chaos\source;

use chaos\SourceException;

class DataCollector
{
    protected $_data = [];

    public function set($source, $id, $data)
    {
        $this->_data[$source][$id] = $data;
    }

    public function get($source, $id)
    {
        if (isset($this->_data[$source][$id])) {
            return $this->_data[$source][$id];
        }
    }
}
