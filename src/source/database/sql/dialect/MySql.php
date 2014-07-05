<?php
namespace chaos\source\database\sql\dialect;

use set\Set;

/**
 * MySQL dialect
 */
class MySql extends \sql\Sql
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '`';

    /**
     * MySQL column type definitions.
     *
     * @var array
     */
    protected $_columns = [
        'id'         => ['use' => 'INT', 'length' => 11, 'increment' => true],
        'string'     => ['use' => 'VARCHAR', 'length' => 255],
        'text'       => ['use' => 'TEXT'],
        'integer'    => ['use' => 'INT', 'length' => 11],
        'biginteger' => ['use' => 'BIGINT'],
        'boolean'    => ['use' => 'TINYINT', 'length' => 1],
        'float'      => ['use' => 'FLOAT'],
        'double'     => ['use' => 'DOUBLE'],
        'decimal'    => ['use' => 'DECIMAL'],
        'date'       => ['use' => 'DATE'],
        'datetime'   => ['use' => 'DATETIME'],
        'timestamp'  => ['use' => 'TIMESTAMP'],
        'time'       => ['use' => 'TIME'],
        'year'       => ['use' => 'YEAR', 'length' => 4],
        'binary'     => ['use' => 'BLOB'],
        'uuid'       => ['use' => 'CHAR', 'length' => 36]
    ];

    /**
     * Meta atrribute syntax
     * By default `'escape'` is false and 'join' is `' '`
     *
     * @var array
     */
    protected $_metas = array(
        'column' => array(
            'charset' => array('keyword' => 'CHARACTER SET'),
            'collate' => array('keyword' => 'COLLATE'),
            'comment' => array('keyword' => 'COMMENT', 'escape' => true)
        ),
        'table' => array(
            'charset' => array('keyword' => 'DEFAULT CHARSET'),
            'collate' => array('keyword' => 'COLLATE'),
            'engine' => array('keyword' => 'ENGINE'),
            'tablespace' => array('keyword' => 'TABLESPACE')
        )
    );

    /**
     * Column contraints
     *
     * @var array
     */
    protected $_constraints = array(
        'primary' => array('template' => 'PRIMARY KEY ({:column})'),
        'foreign_key' => array(
            'template' => 'FOREIGN KEY ({:column}) REFERENCES {:to} ({:toColumn}) {:on}'
        ),
        'index' => array('template' => 'INDEX ({:column})'),
        'unique' => array(
            'template' => 'UNIQUE {:index} ({:column})',
            'key' => 'KEY',
            'index' => 'INDEX'
        ),
        'check' => array('template' => 'CHECK ({:expr})')
    );

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
                ':pow'         => ['format' => 'pow(%s, %s)'],
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
     * @see    chaos\source\database\Structure::column()
     * @param  array $field A field array
     * @return string The SQL column string
     */
    protected function _column($field)
    {
        extract($field);
        if ($type === 'float' && $precision) {
            $use = 'decimal';
        }

        $out = $this->name($name) . ' ' . $use;

        $allowPrecision = preg_match('/^(decimal|float|double|real|numeric)$/',$use);
        $precision = ($precision && $allowPrecision) ? ",{$precision}" : '';

        if ($length && ($allowPrecision || preg_match('/(char|binary|int|year)/',$use))) {
            $out .= "({$length}{$precision})";
        }

        $out .= $this->metas('column', $field, array('charset', 'collate'));

        if (isset($increment) && $increment) {
            $out .= ' NOT NULL AUTO_INCREMENT';
        } else {
            $out .= is_bool($null) ? ($null ? ' NULL' : ' NOT NULL') : '' ;
            $out .= $default ? ' DEFAULT ' . $this->value($default, $field) : '';
        }

        return $out . $this->metas('column', $field, array('comment'));
    }
}
