<?php
namespace chaos\source\mongo;

/**
 * This class is a query container
 */
class Request
{
    /**
     * The query.
     */
    protected $_query = [];

    /**
     * The constructor
     *
     * @param array $query
     */
    public function __construct($query = []) {
        $this->_query = $query;
    }

    /**
     * Set the query
     *
     * @param  array   $query
     * @return Request        Returns a new request with `$query`.
     */
    public function set($query = [])
    {
        return new static($query);
    }

    /**
     * Get the query
     *
     * @return array   Returns the query.
     */
    public function get($source) {
        $query = [];
        foreach ($this->_query as $key => $value) {
            $query[$key] = $this->_normalize($key, $value, $source);
        }
        return $query;
    }


}
