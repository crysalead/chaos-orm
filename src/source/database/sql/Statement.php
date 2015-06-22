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
    protected $_dialect = null;

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
        $defaults = ['dialect' => null];
        $config += $defaults;
        if (!$config['dialect']) {
            throw new SourceException('Missing SQL dialect adapter');
        }
        $this->_dialect = $config['dialect'];
    }

    public function dialect($dialect = null)
    {
        if ($dialect !== null) {
            $this->_dialect = $dialect;
        }
        return $this->_dialect;
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
     * Order formatter helper method
     *
     * @param  string|array $fields The fields.
     * @return string       Formatted fields.
     */

    protected function _order($fields)
    {
        $direction = ' ASC';

        $result = [];
        foreach ($fields as $field => $value) {
            if (!is_int($field)) {
                $result[$field] = $value;
                continue;
            }
            if (preg_match('/^(.*?)\s+((?:a|de)sc)$/i', $value, $match)) {
                $value = $match[1];
                $dir = $match[2];
            } else {
                $dir = $direction;
            }
            $result[$value] = $dir;
        }
        return $result;
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
     * Executes the query.
     *
     * @param  array  $options       An option array.
     * @return object                A `Cursor` instance.
     * @throws chaos\SourceException
     */
    public function execute($options = [])
    {
        $connection = $this->dialect()->connection();
        if (!$connection) {
            throw new SourceException("No valid connection available.");
        }
        return $connection->query($this->toString(), [], $options);
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

    protected function _buildOrder($fields, $paths = [])
    {
        $result = [];

        foreach ($fields as $column => $dir) {
            if (is_int($column)) {
                $column = $dir;
                $dir = ' ASC';
            }
            $dir = preg_match('/^(asc|desc)$/i', $dir) ? " {$dir}" : ' ASC';

            $column = $this->dialect()->name($column, $paths);
            $result[] = "{$column}{$dir}";
        }
        return $this->_buildClause('ORDER BY', join(', ', $result));
    }
}
