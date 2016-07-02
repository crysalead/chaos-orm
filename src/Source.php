<?php
namespace Chaos;

use DateTime;
use Lead\Set\Set;

/**
 * PDO driver adapter base class
 */
class Source
{
    /**
     * Type conversion definitions.
     *
     * @var array
     */
    protected $_handlers = [];

    /**
     * Import/export casting definitions.
     *
     * @var array
     */
    protected $_formatters = [];

    /**
     * Constrcutor.
     *
     * @param  $config array Configuration options. Allowed options:
     *                       - `'handlers'`  : _array_ Casting handlers.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'handlers'   => []
        ];
        $config += $defaults;

        $this->_handlers = Set::merge($this->_handlers(), $config['handlers']);

        $handlers = $this->_handlers;

        $this->formatter('cast', 'id',       $handlers['cast']['integer']);
        $this->formatter('cast', 'serial',   $handlers['cast']['integer']);
        $this->formatter('cast', 'integer',  $handlers['cast']['integer']);
        $this->formatter('cast', 'float',    $handlers['cast']['float']);
        $this->formatter('cast', 'decimal',  $handlers['cast']['decimal']);
        $this->formatter('cast', 'date',     $handlers['cast']['date']);
        $this->formatter('cast', 'datetime', $handlers['cast']['datetime']);
        $this->formatter('cast', 'boolean',  $handlers['cast']['boolean']);
        $this->formatter('cast', 'null',     $handlers['cast']['null']);
        $this->formatter('cast', 'string',   $handlers['cast']['string']);

        $this->formatter('datasource', 'date',      $handlers['datasource']['date']);
        $this->formatter('datasource', 'datetime',  $handlers['datasource']['datetime']);
        $this->formatter('datasource', 'boolean',   $handlers['datasource']['boolean']);
        $this->formatter('datasource', 'null',      $handlers['datasource']['null']);
        $this->formatter('datasource', '_default_', $handlers['datasource']['string']);
    }

    /**
     * Return default cast handlers
     *
     * @return array
     */
    protected function _handlers()
    {
        return [
            'cast' => [
                'string' => function($value, $options = []) {
                    return (string) $value;
                },
                'integer' => function($value, $options = []) {
                    return (integer) $value;
                },
                'float'   => function($value, $options = []) {
                    return (float) $value;
                },
                'decimal' => function($value, $options = []) {
                    $options += ['precision' => 2, 'decimal' => '.', 'separator' => ''];
                    return number_format($value, $options['precision'], $options['decimal'], $options['separator']);
                },
                'boolean' => function($value, $options = []) {
                    return !!$value;
                },
                'date'    => function($value, $options = []) {
                    return $this->convert('cast', 'datetime', $value, ['format' => 'Y-m-d'])->setTime(0, 0, 0);
                },
                'datetime'    => function($value, $options = []) {
                    $options += ['format' => 'Y-m-d H:i:s'];
                    if (is_numeric($value)) {
                        return new DateTime('@' . $value);
                    }
                    if ($value instanceof DateTime) {
                        return $value;
                    }
                    return DateTime::createFromFormat($options['format'], date($options['format'], strtotime($value)));
                },
                'null'    => function($value, $options = []) {
                    return null;
                }
            ],
            'datasource' => [
                'string'   => function($value, $options = []) {
                    return (string) $value;
                },
                'date'     => function($value, $options = []) {
                    if (!$value instanceof DateTime) {
                        $value = new DateTime($value);
                    }
                    return $value->format('Y-m-d');
                },
                'datetime' => function($value, $options = []) {
                    if (!$value instanceof DateTime) {
                        $value = new DateTime($value);
                    }
                    return $value->format('Y-m-d H:i:s');
                },
                'boolean'  => function($value, $options = []) {
                    return !!$value ? '1' : '0';
                },
                'null'     => function($value, $options = []) {
                    return '';
                }
            ]
        ];
    }

    /**
     * Gets/sets a formatter handler.
     *
     * @param  string   $type          The type name.
     * @param  callable $importHandler The callable import handler.
     * @param  callable $exportHandler The callable export handler. If not set use `$importHandler`.
     */
    public function formatter($mode, $type, $handler = null)
    {
        if (func_num_args() === 2) {
          if (isset($this->_formatters[$mode][$type])) {
            return $this->_formatters[$mode][$type];
          } elseif (isset($this->_formatters[$mode]['_default_'])) {
            return $this->_formatters[$mode]['_default_'];
          } else {
            return;
          }
        }
        $this->_formatters[$mode][$type] = $handler;
        return $this;
    }

    /**
     * Gets/sets all formatters.
     *
     */
    public function formatters($formatters = null)
    {
        if (!func_num_args()) {
            return $this->_formatters;
        }
        $this->_formatters = $formatters;
        return $this;
    }

    /**
     * Formats a value according to its definition.
     *
     * @param   string $mode  The format mode (i.e. `'cast'` or `'datasource'`).
     * @param   string $type  The type name.
     * @param   mixed  $value The value to format.
     * @return  mixed         The formated value.
     */
    public function convert($mode, $type, $value, $options = [])
    {
        $formatter = null;

        if (isset($this->_formatters[$mode][$type])) {
            $formatter = $this->_formatters[$mode][$type];
        } elseif (isset($this->_formatters[$mode]['_default_'])) {
            $formatter = $this->_formatters[$mode]['_default_'];
        }
        return $formatter ? $formatter($value, $options) : $value;
    }
}
