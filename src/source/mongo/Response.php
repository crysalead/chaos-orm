<?php
namespace chaos\source\mongo;

/**
 * This class is a wrapper around database result and can be used to iterate over it.
 */
class Response extends chaos\source\Response
{
    /**
     * The bound cursor.
     */
    protected $_data = [];

    /**
     * The bound cursor.
     */
    protected $_cursor = null;

    /**
     * The constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'   => [],
            'cursor' => null
        ];
        $config += $defaults;
        $this->_cursor = $config['cursor'];
        $this->_data = $config['data'];
    }

    /**
     * Returns the bound data.
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Returns the bound cursor.
     */
    public function cursor()
    {
        return $this->_cursor;
    }

    /**
     * Fetches the result from the resource and caches it.
     *
     * @return boolean Return `true` on success or `false` if it is not valid.
     */
    protected function _fetch()
    {
        return $this->_data ? $this->_fetchArray() : $this->_fetchCursor();
    }

    protected function _fetchCursor()
    {
        if (key($this->_data) === null) {
            return false;
        }
        $this->_current = current($this->_data);
        $this->_key = key($this->_data);
        next($this->_data);
        return true;
    }

    protected function _fetchCursor()
    {
        if (!$this->_cursor) {
            return false;
        }
        if (!$this->_started) {
            $this->_cursor->rewind();
        }
        if (!$this->_cursor->valid()) {
            return false;
        }
        $this->_current = $this->_cursor->current();
        $this->_key = $this->_cursor->key();
        $this->_cursor->next();
        return true;
    }

    /**
     * Close the resource.
     */
    public function close()
    {
        unset($this->_cursor);
        $this->_cursor = null;
        $this->_data = [];
    }

}
