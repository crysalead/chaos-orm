<?php
namespace chaos\source\database\sql\statement;

use RuntimeException;

/**
 * SQL CRUD helper
 */
class Select
{
    /**
     * The generated SQL query.
     *
     * @var string
     */
    protected $_data = [];

    /**
     * Pointer to the dialect adapter.
     *
     * @var object
     */
    protected $_adapter = null;

    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [
        'select' => null,
        'fields' => null,
        'from'   => null,
        'joins'  => null,
        'where'  => null,
        'group'  => null,
        'having' => null,
        'order'  => null
    ];

    /**
     * Constructor
     *
     * @param  array $config The config array. The options is:
     *                       - 'adapter' `object` a dialect adapter.
     * @throws RuntimeException
     */
    public function __construct($config = [])
    {
        $defaults = ['adapter' => null];
        $config += $defaults;
        if (!$config['adapter']) {
            throw new RuntimeException('Missing dialect adapter');
        }
        $this->_adapter = $config['adapter'];
    }

    public function __call($name, $params)
    {
        $this->_parts[$name] = $params;
        return $this;
    }

    /**
     * Render the SQL statement
     */
    public function __toString()
    {
        foreach ($this->_parts as $key => $value) {
            $query[] = $this->_adapter->$key($value);
        }
        return join(' ', array_filter($query));
    }
}
