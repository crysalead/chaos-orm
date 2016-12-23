<?php
namespace Chaos;

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
    protected $_collection = [];

    /**
     * Creates a new record object with default values.
     *
     * @param mixed $collection The data.
     */
    public function __construct($collection)
    {
        $this->_collection = $collection;
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
        return $this->get();
    }

    /**
     * Executes the query and returns the result.
     *
     * @param  array  $options The fetching options.
     * @return object          An iterator instance.
     */
    public function get($options = [])
    {
        return $this->_collection;
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
        return (int) count($this->_collection);
    }
}
