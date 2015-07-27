<?php
namespace chaos\model;

use chaos\SourceException;

class Cursor implements \Iterator
{
    /**
     * The optionnal bound data.
     */
    protected $_data = [];

    /**
     * The bound resource.
     */
    protected $_resource = null;

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
     * Indicates whether the cursor is valid or not.
     *
     * @var boolean
     */
    protected $_error = false;

    /**
     * Stores the error number.
     *
     * @var mixed
     */
    protected $_errno = 0;

    /**
     * Stores the error message.
     *
     * @var mixed
     */
    protected $_errmsg = '';

    /**
     * If the result resource has been initialized
     */
    protected $_key = null;

    /**
     * The constructor
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'     => [],
            'resource' => null,
            'error'    => false,
            'errno'    => 0,
            'errmsg'   => ''
        ];
        $config += $defaults;
        $this->_resource = $config['resource'];
        $this->_data = $config['data'];
        $this->_error = $config['error'];
        $this->_errno = $config['errno'];
        $this->_errmsg = $config['errmsg'];

        if (!$this->_resource && !$this->_data) {
            throw new SourceException("Invalid data or ressource");
        }
    }

    /**
     * Returns the bound data.
     *
     * @return array
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Returns the bound resource.
     *
     * @return ressource
     */
    public function resource()
    {
        return $this->_resource;
    }

    /**
     * Returns the error value.
     *
     * @return boolean
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Returns the error number.
     *
     * @return mixed
     */
    public function errno()
    {
        return $this->_errno;
    }

    /**
     * Returns the error message.
     *
     * @return string
     */
    public function errmsg()
    {
        return $this->_errmsg;
    }

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
        reset($this->_data);
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
        if ($this->_data) {
            return $this->_fetchArray();
        }
        if($this->_resource) {
            return $this->_fetchResource();
        }
        return false;
    }

    /**
     * Fetches the result from the data array.
     *
     * @return boolean Return `true` on success or `false` otherwise.
     */
    protected function _fetchArray()
    {
        if (key($this->_data) === null) {
            return false;
        }
        $this->_current = current($this->_data);
        $this->_key = key($this->_data);
        next($this->_data);
        return true;
    }

    /**
     * Close the resource.
     */
    public function close()
    {
        unset($this->_resource);
        $this->_resource = null;
        $this->_data = [];
    }

    /**
     * The destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    protected function _fetchResource()
    {
        throw new SourceException("This cursor doesn't support ressource");
    }
}
