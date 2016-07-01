<?php
namespace Chaos;

/**
 * The `Map` class can store object by key.
 */
class Map
{
    /**
     * The map array scoped by names.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Collects an object.
     *
     * @param  string $instance An instance.
     * @param  mixed  $data     The data to map to the document.
     * @return self             Return `$this`.
     */
    public function set($instance, $data)
    {
        $id = spl_object_hash($instance);
        $this->_data[$id] = $data;
        return $this;
    }

    /**
     * Gets a collected object.
     *
     * @param  string $instance The instance to look up.
     * @return mixed            The collected data.
     */
    public function get($instance)
    {
        $id = spl_object_hash($instance);
        if (isset($this->_data[$id])) {
            return $this->_data[$id];
        }
        throw new ChaosException("No collected data associated to the key.");
    }

    /**
     * Uncollects an object.
     *
     * @param string $instance The instance to remove.
     * @return self             Return `$this`.
     */
    public function remove($instance)
    {
        $id = spl_object_hash($instance);
        unset($this->_data[$id]);
        return $this;
    }

    /**
     * Checks if an object with a specific ID has already been collected.
     *
     * @param  string  $instance The instance to look up.
     * @return boolean           Returns `true` if exists, `false` otherwise.
     */
    public function has($instance)
    {
        $id = spl_object_hash($instance);
        return isset($this->_data[$id]);
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
