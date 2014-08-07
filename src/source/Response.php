<?php
namespace chaos\source;

abstract class Response implements \Iterator
{
    /**
     * Contains the current element of the result set.
     */
    protected $_current = false;

    /**
     * Setted to `true` when the collection has begun iterating.
     *
     * @var integer
     */
    protected $_started = false;

    /**
     * If the result resource has been initialized
     */
    protected $_init = false;

    /**
     * Indicates whether the current position is valid or not.
     *
     * @var boolean
     */
    protected $_valid = false;

    /**
     * If the result resource has been initialized
     */
    protected $_key = null;

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        if (!$this->_init) {
            $this->_valid = $this->fetch();
        }
        return $this->_valid;
    }

    /**
     * Rewinds the result set to the first position.
     */
    public function rewind()
    {
        $this->_started = false;
        $this->_key = null;
        $this->_current = false;
        $this->_init = false;
    }

    /**
     * Contains the current result.
     *
     * @return array The current result (or `null` if there is none).
     */
    public function current()
    {
        if (!$this->_init) {
            $this->fetch();
        }
        $this->_started = true;
        return $this->_current;
    }

    /**
     * Returns the current key position on the result.
     *
     * @return integer The current key.
     */
    public function key()
    {
        if (!$this->_init) {
            $this->fetch();
        }
        $this->_started = true;
        return $this->_key;
    }

    /**
     * Fetches the next element from the resource.
     *
     * @return mixed The next result (or `false` if there is none).
     */
    public function next()
    {
        if ($this->_started === false) {
            return $this->current();
        }
        $this->_valid = $this->fetch();
        if (!$this->_valid) {
            $this->_key = null;
            $this->_current = false;
        }
        return $this->current();
    }

    /**
     * Fetches the current element from the resource.
     *
     * @return boolean Return `true` on success or `false` otherwise.
     */
    protected function fetch()
    {
        $this->_init = true;
        return $this->_fetch();
    }

    /**
     * The destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    abstract protected function _fetch();
}
