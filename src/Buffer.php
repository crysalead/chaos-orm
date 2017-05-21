<?php
namespace Chaos\ORM;

use ArrayIterator;
use IteratorAggregate;

/**
 * The Query wrapper.
 */
class Buffer implements IteratorAggregate
{
    /**
     * The data.
     *
     * @var mixed
     */
    protected $_data = [];

    /**
     * Creates a new record object with default values.
     *
     * @param mixed $handler The handler.
     */
    public function __construct($handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Log calls
     */
    public function __call($name, $params = [])
    {
        $this->_data[$name][] = $params;
    }

    /**
     * Executes the query and returns the result (must implements the `Iterator` interface).
     *
     * (Automagically called on `foreach`)
     *
     * @return object An iterator instance.
     */
    public function getIterator()
    {
        $data = $this->get();
        return is_array($data) ? new ArrayIterator($data) : $data;
    }

    /**
     * Executes the query and returns the result.
     *
     * @param  array  $options The fetching options.
     * @return object          An iterator instance.
     */
    public function get($options = [])
    {
        $handler = $this->_handler;
        return $handler($this->_data, $options);
    }

    /**
     * Alias for `get()`
     *
     * @return object An iterator instance.
     */
    public function all($options = [])
    {
        return $this->get($options);
    }

    /**
     * Executes the query and returns the first result only.
     *
     * @return object An entity instance.
     */
    public function first($options = [])
    {
        $result = $this->get($options);
        return is_object($result) ? $result->rewind() : reset($result);
    }

    /**
     * Executes the query and returns the count number.
     *
     * @return integer The number of rows in result.
     */
    public function count()
    {
        return (int) count($this->get());
    }
}
