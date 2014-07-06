<?php
namespace chaos\source\database\sql\statement;

use RuntimeException;

/**
 * SQL CRUD helper
 */
class CreateTable
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
        'createTable' => [],
        'table'       => false,
        'columns'     => false,
        'constraints' => false,
        'comment'     => false
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
        if (!isset($this->_parts[$name])) {
            throw new RuntimeException("Invalid command {$name}");
        }
        $this->_parts[$name][] = $params;
        return $this;
    }

    /**
     * Render the SQL statement
     */
    public function __toString()
    {
        foreach ($this->_parts as $key => $value) {
            if (is_array($value)) {
                $params = $value ? end($value) : [];
                $query[] = call_user_func_array([$this->_adapter, $key], $params);
            }
        }
        return join(' ', array_filter($query));
    }
}
