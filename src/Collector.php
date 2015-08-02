<?php
namespace chaos;

use chaos\ChaosException;

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
     * @param string $scope The scope name.
     * @param string $id    The ID to look up.
     * @param mixed         The data to collect.
     */
    public function set($scope, $id, $data)
    {
        $this->_data[$scope][$id] = $data;
    }

    /**
     * Checks if an object with a specific ID has already been collected.
     *
     * @param  string $scope The scope name.
     * @param  string $id    The ID to look up.
     * @return boolean       Returns `true` if exists, `false` otherwise.
     */
    public function exists($scope, $id)
    {
        return isset($this->_data[$scope][$id]);
    }

    /**
     * Gets a collected object.
     *
     * @param  string $scope The scope name.
     * @param  string $id    The ID to look up.
     * @return mixed         The collected data.
     */
    public function get($scope, $id)
    {
        if (isset($this->_data[$scope][$id])) {
            return $this->_data[$scope][$id];
        }
        throw new ChaosException("No collected data for `'{$scope}'` with ID `'{$id}'` in this collector.");
    }
}
