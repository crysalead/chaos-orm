<?php
namespace chaos\source\database\sql\dialect;

use set\Set;

/**
 * Sqlite3 dialect
 */
class Sqlite3 extends \sql\Sql
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

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
}
