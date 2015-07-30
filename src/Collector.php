<?php
namespace chaos;

class Collector
{
    protected $_data = [];

    public function set($source, $id, $data)
    {
        $this->_data[$source][$id] = $data;
    }

    public function has($source, $id)
    {
        return isset($this->_data[$source][$id]);
    }

    public function get($source, $id)
    {
        if (isset($this->_data[$source][$id])) {
            return $this->_data[$source][$id];
        }
    }
}
