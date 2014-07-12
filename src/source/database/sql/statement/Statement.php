<?php
namespace chaos\source\database\sql\statement;

use chaos\SourceException;

/**
 * Statement
 */
class Statement
{
	/**
     * Pointer to the dialect adapter.
     *
     * @var object
     */
    protected $_sql = null;

    /**
     * The SQL parts.
     *
     * @var string
     */
    protected $_parts = [];

    /**
     * Constructor
     *
     * @param  array $config The config array. The options is:
     *                       - 'adapter' `object` a dialect adapter.
     * @throws RuntimeException
     */
    public function __construct($config = [])
    {
        $defaults = ['sql' => null];
        $config += $defaults;
        if (!$config['sql']) {
            throw new SourceException('Missing SQL dialect adapter');
        }
        $this->_sql = $config['sql'];
    }

    public function sql($adapter = null) {
        if ($adapter !== null) {
            $this->_sql = $adapter;
        }
        return $this->_sql;
    }

    public function __call($name, $params)
    {
        throw new SourceException("Invalid clause `{$name}` for `" . get_called_class() . "`");
    }

    /**
     * String representation of this object
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (Exception $exception) {
            return '';
        }
    }
}