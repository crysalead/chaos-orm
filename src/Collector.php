<?php
namespace Chaos;

/**
 * The `Collector` class ensures single references of objects through the Identity Map pattern.
 */
class Collector
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
     * @param  string $uuid The UUID to look up.
     * @param  mixed        The data to collect.
     * @return self         Return `$this`.
     */
    public function set($uuid, $data)
    {
        $this->_data[$uuid] = $data;
        return $this;
    }

    /**
     * Gets a collected object.
     *
     * @param  string $uuid The UUID to look up.
     * @return mixed        The collected data.
     */
    public function get($uuid)
    {
        if (isset($this->_data[$uuid])) {
            return $this->_data[$uuid];
        }
        throw new ChaosException("No collected data with UUID `'{$uuid}'` in this collector.");
    }

    /**
     * Uncollects an object.
     *
     * @param  string $uuid The UUID to remove.
     * @return self         Return `$this`.
     */
    public function remove($uuid)
    {
        unset($this->_data[$uuid]);
        return $this;
    }

    /**
     * Checks if an object with a specific ID has already been collected.
     *
     * @param  string  $uuid The UUID to look up.
     * @return boolean       Returns `true` if exists, `false` otherwise.
     */
    public function has($uuid)
    {
        return isset($this->_data[$uuid]);
    }
}
