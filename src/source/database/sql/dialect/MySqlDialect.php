<?php
namespace chaos\source\database\sql\dialect;

use set\Set;

/**
 * MySQL dialect
 */
class MySqlDialect extends \chaos\source\database\sql\Sql
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '`';

    /**
     * Meta atrribute syntax
     * By default `'escape'` is false and 'join' is `' '`
     *
     * @var array
     */
    protected $_metas = [
        'column' => [
            'charset' => ['keyword' => 'CHARACTER SET'],
            'collate' => ['keyword' => 'COLLATE'],
            'comment' => ['keyword' => 'COMMENT', 'escape' => true]
        ],
        'table' => [
            'charset' => ['keyword' => 'DEFAULT CHARSET'],
            'collate' => ['keyword' => 'COLLATE'],
            'engine' => ['keyword' => 'ENGINE'],
            'tablespace' => ['keyword' => 'TABLESPACE']
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
        'index' => ['template' => 'INDEX ({:column})'],
        'unique' => [
            'template' => 'UNIQUE {:index} ({:column})',
            'key' => 'KEY',
            'index' => 'INDEX'
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
                ':concat'      => ['format' => 'CONCAT(%s, %s)'],
                ':pow'         => ['format' => 'POW(%s, %s)'],
                '#'            => ['format' => '%s ^ %s'],
                ':regex'       => ['format' => '%s REGEXP %s'],
                ':rlike'       => [],
                ':sounds like' => [],
                // Set operators
                ':union'       => ['type' => 'set'],
                ':union all'   => ['type' => 'set'],
                ':minus'       => ['type' => 'set'],
                ':except'      => ['name' => 'MINUS', 'type' => 'set']
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
            $use = 'decimal';
        }

        $column = $this->escape($name) . ' ' . $use;

        $allowPrecision = preg_match('/^(decimal|float|double|real|numeric)$/',$use);
        $precision = ($precision && $allowPrecision) ? ",{$precision}" : '';

        if ($length && ($allowPrecision || preg_match('/(char|binary|int|year)/',$use))) {
            $column .= "({$length}{$precision})";
        }

        $result = [$column];
        $result[] = $this->metas('column', $field, ['charset', 'collate']);

        if (isset($serial) && $serial) {
            $result[] = 'NOT NULL AUTO_INCREMENT';
        } else {
            $result[] = is_bool($null) ? ($null ? 'NULL' : 'NOT NULL') : '' ;
            if ($default) {
                if (is_array($default)) {
                    list($operator, $default) = each($default);
                } else {
                    $operator = ':value';
                }
                $result[] = 'DEFAULT ' . $this->format($operator, $default, $type);
            }
        }

        $result[] = $this->metas('column', $field, ['comment']);
        return join(' ', array_filter($result));
    }
}
