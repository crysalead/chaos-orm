<?php
namespace chaos\source\database\sql;

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
    protected $_parts = [
        'flags' => ''
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
        $defaults = ['sql' => null];
        $config += $defaults;
        if (!$config['sql']) {
            throw new SourceException('Missing SQL dialect adapter');
        }
        $this->_sql = $config['sql'];
    }

    public function sql($adapter = null)
    {
        if ($adapter !== null) {
            $this->_sql = $adapter;
        }
        return $this->_sql;
    }

    public function data($name, $value = null)
    {
        if (func_num_args() === 2) {
            return $this->_parts[$name] = $value;
        }
        return isset($this->_parts[$name]) ? $this->_parts[$name] : null;
    }

    protected function setFlag($flag, $enable = true)
    {
        return $this->_parts['flags'][$flag] = $enable;
    }

    /**
     * Helper method
     *
     * @param  string|array $fields The fields.
     * @return string       Formatted fields.
     */
    protected function _sort($fields, $direction = true)
    {
        $direction = $direction ? ' ASC' : '';

        if (is_string($fields)) {
            if (preg_match('/^(.*?)\s+((?:a|de)sc)$/i', $fields, $match)) {
                $fields = $match[1];
                $direction = $match[2];
            }
            $fields = [$fields => $direction];
        }

        $result = [];

        foreach ($fields as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = $direction;
            }
            $dir = preg_match('/^(asc|desc)$/i', $dir) ? " {$dir}" : $direction;

            $column = $this->sql()->name($column);
            $result[] = "{$column}{$dir}";
        }
        return $fields = join(', ', $result);
    }

    /**
     * Throws an error for invalid clauses.
     *
     * @param string $name   The name of the matcher.
     * @param array  $params The parameters to pass to the matcher.
     */
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

    protected function _buildClause($clause, $expression)
    {
        return $expression ? " {$clause} {$expression}": '';
    }

    protected function _buildFlags($flags)
    {
        $flags = array_filter($flags);
        return $flags ? ' ' . join(' ', array_keys($flags)) : '';
    }


    protected function _buildFlag($flag, $value)
    {
        return $value ? " {$flag}": '';
    }

    protected function _buildChunk($sql)
    {
        return $sql ? " {$sql}" : '';
    }

}
