<?php
namespace Chaos\ORM;

use InvalidArgumentException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
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

        $this->_handlers = Set::extend($this->_handlers(), $config['handlers']);

        $handlers = $this->_handlers;

        $this->formatter('array', 'object',    $handlers['array']['object']);
        $this->formatter('array', 'integer',   $handlers['array']['integer']);
        $this->formatter('array', 'float',     $handlers['array']['float']);
        $this->formatter('array', 'decimal',   $handlers['array']['string']);
        $this->formatter('array', 'date',      $handlers['array']['date']);
        $this->formatter('array', 'datetime',  $handlers['array']['datetime']);
        $this->formatter('array', 'boolean',   $handlers['array']['boolean']);
        $this->formatter('array', 'null',      $handlers['array']['null']);
        $this->formatter('array', 'json',      $handlers['array']['json']);

        $this->formatter('cast', 'object',   $handlers['cast']['object']);
        $this->formatter('cast', 'integer',  $handlers['cast']['integer']);
        $this->formatter('cast', 'float',    $handlers['cast']['float']);
        $this->formatter('cast', 'decimal',  $handlers['cast']['decimal']);
        $this->formatter('cast', 'date',     $handlers['cast']['date']);
        $this->formatter('cast', 'datetime', $handlers['cast']['datetime']);
        $this->formatter('cast', 'boolean',  $handlers['cast']['boolean']);
        $this->formatter('cast', 'null',     $handlers['cast']['null']);
        $this->formatter('cast', 'json',     $handlers['cast']['json']);
        $this->formatter('cast', 'string',   $handlers['cast']['string']);

        $this->formatter('datasource', 'object',    $handlers['datasource']['object']);
        $this->formatter('datasource', 'integer',   $handlers['datasource']['integer']);
        $this->formatter('datasource', 'float',     $handlers['datasource']['float']);
        $this->formatter('datasource', 'date',      $handlers['datasource']['date']);
        $this->formatter('datasource', 'datetime',  $handlers['datasource']['datetime']);
        $this->formatter('datasource', 'boolean',   $handlers['datasource']['boolean']);
        $this->formatter('datasource', 'null',      $handlers['datasource']['null']);
        $this->formatter('datasource', 'json',      $handlers['datasource']['json']);
        $this->formatter('datasource', '_default_', $handlers['datasource']['string']);
    }

    /**
     * Return default cast handlers
     *
     * @return array
     */
    protected function _handlers()
    {
        $gmstrtotime = function ($value) {
            $TZ = date_default_timezone_get();
            if ($TZ === 'UTC') {
                return strtotime($value);
            }
            date_default_timezone_set('UTC');
            $time = strtotime($value);
            date_default_timezone_set($TZ);
            return $time;
        };

        return [
            'array' => [
                'object' => function($value, $column) {
                    return $value->to('array', $column);
                },
                'string' => function($value, $column) {
                    return (string) $value;
                },
                'integer' => function($value, $column) {
                    return (int) $value;
                },
                'float' => function($value, $column) {
                    return (float) $value;
                },
                'date' => function($value, $column) {
                    return $this->convert('array', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime' => function($value, $column) use ($gmstrtotime) {
                    $column += ['format' => 'Y-m-d H:i:s'];
                    $format = $column['format'];
                    if ($value instanceof DateTime) {
                        return $value->format($format);
                    }
                    return gmdate($format, (integer) (is_numeric($value) ? $value : $gmstrtotime($value)));
                },
                'boolean' => function($value, $column) {
                    return $value;
                },
                'null' => function($value, $column) {
                    return;
                },
                'json' => function($value, $column) {
                    return is_array($value) ? $value : $value->to('array', $column);
                }
            ],
            'cast' => [
                'object' => function($value, $column, $options) {
                    return is_array($value) ? new Document([
                        'schema' => $options['schema'] ?? null,
                        'basePath' => $options['basePath'] ?? null,
                        'data' => $value
                    ]) : $value;
                },
                'string' => function($value, $column, $options) {
                    return (string) $value;
                },
                'integer' => function($value, $column, $options) {
                    return (integer) $value;
                },
                'float'   => function($value, $column, $options) {
                    return (float) $value;
                },
                'decimal' => function($value, $column, $options) {
                    $column += ['precision' => 2, 'decimal' => '.', 'separator' => ''];
                    $value = is_numeric($value) ? $value : 0;
                    return number_format($value, $column['precision'], $column['decimal'], $column['separator']);
                },
                'boolean' => function($value, $column, $options) {
                    return !!$value;
                },
                'date'    => function($value, $column, $options) {
                    return $this->convert('cast', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime'    => function($value, $column, $options) use ($gmstrtotime) {
                    $column += ['format' => 'Y-m-d H:i:s'];
                    if ($value instanceof DateTime) {
                        return $value;
                    }
                    if ($value instanceof DateTimeImmutable) {
                        $dateTime = new DateTime('', $value->getTimezone());
                        $dateTime->setTimestamp($value->getTimestamp());
                        return $dateTime;
                    }
                    $timestamp = is_numeric($value) ? $value : $gmstrtotime($value);
                    if ($timestamp < 0 || $timestamp === false) {
                        return;
                    }
                    return DateTime::createFromFormat("!{$column['format']}", gmdate($column['format'], (integer) $timestamp), new DateTimeZone('UTC'));
                },
                'null'    => function($value, $column, $options) {
                    return null;
                },
                'json' => function($value, $column, $options) {
                    return is_string($value) ? json_decode($value, true) : $value;
                }
            ],
            'datasource' => [
                'object'   => function($value, $column) {
                    return $value->to('plain'); // Unexisting handlers will simply return raw data
                },
                'string'   => function($value, $column) {
                    return (string) $value;
                },
                'integer' => function($value, $column) {
                    return (integer) $value;
                },
                'float'   => function($value, $column) {
                    return (float) $value;
                },
                'date'     => function($value, $column) {
                    return $this->convert('datasource', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime' => function($value, $column) use ($gmstrtotime) {
                    $column += ['format' => 'Y-m-d H:i:s'];
                    if ($value instanceof DateTime) {
                        $date = $value->format($column['format']);
                    } else {
                        $timestamp = is_numeric($value) ? $value : $gmstrtotime($value);
                        if ($timestamp < 0 || $timestamp === false) {
                            throw new InvalidArgumentException("Invalid date `{$value}`, can't be parsed.");
                        }
                        $date = gmdate($column['format'], (integer) $timestamp);
                    }
                    return $date;
                },
                'boolean'  => function($value, $column) {
                    return !!$value ? '1' : '0';
                },
                'null'     => function($value, $column) {
                    return '';
                },
                'json'     => function($value, $column) {
                    if (is_object($value)) {
                        $value = $value->data();
                    }
                    return json_encode($value);
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
     * Formats a value according to its type.
     *
     * @param  string $mode    The format mode (i.e. `'cast'` or `'datasource'`).
     * @param  string $type    The field name.
     * @param  mixed  $data    The value to format.
     * @param  array  $column  The column options to pass the the formatter handler.
     * @param  array  $options The options to pass the the formatter handler (for `'cast'` mode only).
     * @return mixed           The formated value.
     */
    public function convert($mode, $type, $data, $column = [], $options = [])
    {
        $formatter = null;
        $type = $data === null ? 'null' : $type;
        if (isset($this->_formatters[$mode][$type])) {
            $formatter = $this->_formatters[$mode][$type];
        } elseif (isset($this->_formatters[$mode]['_default_'])) {
            $formatter = $this->_formatters[$mode]['_default_'];
        }
        return $formatter ? $formatter($data, $column, $options) : $data;
    }
}
