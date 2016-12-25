<?php
namespace Chaos\ORM;

/**
 * The `Map` class can store object by key.
 */
class Map
{
    /**
     * The keys array.
     *
     * @var array
     */
    protected $_keys = [];

    /**
     * The map array.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Collects an object.
     *
     * @param  mixed $value A value.
     * @param  mixed $data  The data to map to the value.
     * @return self         Return `$this`.
     */
    public function set($value, $data)
    {
        $id = is_object($value) ? spl_object_hash($value) : $value;
        $this->_keys[$id] = $value;
        $this->_data[$id] = $data;
        return $this;
    }

    /**
     * Gets a collected object.
     *
     * @param  mixed $value A value.
     * @return mixed        The collected data.
     */
    public function get($value)
    {
        $id = is_object($value) ? spl_object_hash($value) : $value;
        if (isset($this->_data[$id])) {
            return $this->_data[$id];
        }
        throw new ChaosException("No collected data associated to the key.");
    }

    /**
     * Uncollects an object.
     *
     * @param  mixed $value A value.
     * @return self         Return `$this`.
     */
    public function remove($value)
    {
        $id = is_object($value) ? spl_object_hash($value) : $value;
        unset($this->_keys[$id]);
        unset($this->_data[$id]);
        return $this;
    }

    /**
     * Checks if an object with a specific ID has already been collected.
     *
     * @param  mixed   $value A value.
     * @return boolean        Returns `true` if exists, `false` otherwise.
     */
    public function has($value)
    {
        $id = is_object($value) ? spl_object_hash($value) : $value;
        return isset($this->_data[$id]);
    }

    /**
     * Return the keys.
     *
     * @return array Returns the contained keys.
     */
    public function keys()
    {
        return array_values($this->_keys);
    }

    /**
     * Returns the size of the Map.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->_data);
    }
}
