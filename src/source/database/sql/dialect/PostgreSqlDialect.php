<?php
namespace chaos\source\database\sql\dialect;

use set\Set;

/**
 * PostgreSQL dialect
 */
class PostgreSqlDialect extends \chaos\source\database\sql\Sql
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

    /**
     * Column/table metas
     * By default `'escape'` is false and 'join' is `' '`
     *
     * @var array
     */
    protected $_metas = [
        'table' => [
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
        'foreign_key' => [
            'template' => 'FOREIGN KEY ({:column}) REFERENCES {:to} ({:toColumn}) {:on}'
        ],
        'unique' => [
            'template' => 'UNIQUE {:index} ({:column})'
        ],
        'check' => ['template' => 'CHECK ({:expr})']
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
                ':regex'          => ['format' => '%s ~ %s'],
                ':regexi'         => ['format' => '%s ~* %s'],
                ':not regex'      => ['format' => '%s !~ %s'],
                ':not regexi'     => ['format' => '%s !~* %s'],
                ':similar to'     => [],
                ':not similar to' => [],
                ':square root'    => ['format' => '|/ %s'],
                ':cube root'      => ['format' => '||/ %s'],
                ':fact'           => ['format' => '!! %s'],
                '|/'              => ['format' => '|/ %s'],
                '||/'             => ['format' => '||/ %s'],
                '!!'              => ['format' => '!! %s'],
                ':concat'         => ['format' => '%s || %s'],
                ':pow'            => ['format' => '%s ^ %s'],
                '#'               => [],
                '@'               => ['format' => '@ %s'],
                '<@'              => [],
                '@>'              => [],
                // Set operators
                ':union'          => ['type' => 'set'],
                ':union all'      => ['type' => 'set'],
                ':except'         => ['type' => 'set'],
                ':except all'     => ['type' => 'set'],
                ':intersect'      => ['type' => 'set'],
                ':intersect all'  => ['type' => 'set']
            ],
            'types' => [
                'boolean' => [
                    'core' => function($value, $params = []) { return $value === 't'; },
                    'db' => function($value, $params = []) { return $value ? 't' : 'f'; }
                ]
            ]
        ];

        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }
}
