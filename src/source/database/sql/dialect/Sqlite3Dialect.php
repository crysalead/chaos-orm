<?php
namespace chaos\source\database\sql\dialect;

use set\Set;
use chaos\SourceException;

/**
 * Sqlite3 dialect
 */
class Sqlite3Dialect extends \chaos\source\database\sql\Sql
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

    /**
     * Sqlite3 column type definitions.
     *
     * @var array
     */
    protected $_types = [
        'id'         => ['use' => 'integer'],
        'string'     => ['use' => 'text', 'length' => 255],
        'text'       => ['use' => 'text'],
        'integer'    => ['use' => 'integer'],
        'biginteger' => ['use' => 'integer'],
        'boolean'    => ['use' => 'numeric'],
        'float'      => ['use' => 'real'],
        'double'     => ['use' => 'real'],
        'decimal'    => ['use' => 'numeric'],
        'date'       => ['use' => 'numeric'],
        'datetime'   => ['use' => 'numeric'],
        'timestamp'  => ['use' => 'numeric'],
        'time'       => ['use' => 'numeric'],
        'year'       => ['use' => 'numeric'],
        'binary'     => ['use' => 'blob'],
        'uuid'       => ['use' => 'text', 'length' => 36]
    ];

    /**
     * Column specific metas used on table creating
     * By default `'quote'` is false and 'join' is `' '`
     *
     * @var array
     */
    protected $_metas = [
        'column' => [
            'collate' => ['keyword' => 'COLLATE', 'escape' => true]
        ]
    ];
    /**
     * Column contraints
     *
     * @var array
     */
    protected $_constraints = [
        'primary' => ['template' => 'PRIMARY KEY ({:column})'],
        'foreign key' => [
            'template' => 'FOREIGN KEY ({:foreignKey}) REFERENCES {:to} ({:primaryKey}) {:on}'
        ],
        'unique' => [
            'template' => 'UNIQUE {:index} ({:column})'
        ],
        'check' => ['template' => '{:constraint} CHECK ({:expr})']
    ];

    /**
     * Constructor
     *
     * @param array $config The config array
     */
    public function __construct($config = [])
    {
        $defaults = [
            'operators' => [
                ':glob'      => [],
                ':concat'    => ['format' => '%s || %s'],
                // Set operators
                ':union'     => ['type' => 'set'],
                ':union all' => ['type' => 'set'],
                ':except'    => ['type' => 'set'],
                ':intersect' => ['type' => 'set']
            ]
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }

    /**
     * Helper for creating columns
     *
     * @see    chaos\source\sql\Sql::column()
     * @param  array $field A field array
     * @return string The SQL column string
     */
    protected function _column($field)
    {
        extract($field);
        if ($type === 'float' && $precision) {
            $use = 'numeric';
        }

        $out = $this->name($name) . ' ' . $use;

        $allowPrecision = preg_match('/^(integer|real|numeric)$/',$use);
        $precision = ($precision && $allowPrecision) ? ",{$precision}" : '';

        if ($length && ($allowPrecision || $use === 'text')) {
            $out .= "({$length}{$precision})";
        }

        $out .= $this->_buildMetas('column', $field, array('collate'));

        if ($type !== 'id') {
            $out .= is_bool($null) ? ($null ? ' NULL' : ' NOT NULL') : '' ;
            $out .= $default ? ' DEFAULT ' . $this->value($default, $field) : '';
        }

        return $out;
    }
}
