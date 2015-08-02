<?php
namespace chaos;

use chaos\ChaosException;

class Cursor implements \Iterator
{
    /**
     * The optionnal bound data.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * The bound resource.
     *
     * @var resource
     */
    protected $_resource = null;

    /**
     * Indicates whether the fetching has been started.
     *
     * @var boolean
     */
    protected $_started = false;

    /**
     * Indicates whether the resource has been initialized.
     *
     * @var boolean
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
     * @var integer
     */
    protected $_errno = 0;

    /**
     * Stores the error message.
     *
     * @var string
     */
    protected $_errmsg = '';

    /**
     * Contains the current key of the cursor.
     *
     * @var mixed
     */
    protected $_key = null;

    /**
     * Contains the current value of the cursor.
     *
     * @var mixed
     */
    protected $_current = false;

    /**
     * `Cursor` constructor.
     *
     * @param array $config Possible values are:
     *                      - `'data'`     _array_   : A data array.
     *                      - `'resource'` _resource_: The resource to fetch on.
     *                      - `'error'`    _boolean_ : A error boolean flag.
     *                      - `'errno'`    _mixed_   : An error code number.
     *                      - `'errmsg'`   _string_  : A full string error message.
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
            $this->_valid = $this->_fetch();
        }
        return $this->_valid;
    }

    /**
     * Rewinds the cursor to its first position.
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
     * Returns the current value.
     *
     * @return mixed The current value (or `null` if there is none).
     */
    public function current()
    {
        if (!$this->_init) {
            $this->_fetch();
        }
        $this->_started = true;
        return $this->_current;
    }

    /**
     * Returns the current key value.
     *
     * @return integer The current key value.
     */
    public function key()
    {
        if (!$this->_init) {
            $this->_fetch();
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
        $this->_valid = $this->_fetch();
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
    protected function _fetch()
    {
        $this->_init = true;
        if($this->_resource) {
            return $this->_fetchResource();
        } else {
            return $this->_fetchArray();
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
     * Closes the resource.
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

    /**
     * The fetching method for resource based cursor.
     */
    protected function _fetchResource()
    {
        throw new ChaosException("This cursor doesn't support ressource");
    }
}
