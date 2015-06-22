<?php
namespace chaos\source\database\sql\dialect;

use set\Set;

/**
 * PostgreSQL dialect
 *
 * - array_to_json(pg_array_result)
 * - array_to_json(hstore_to_array(value))
 */
class PostgreSqlDialect extends \chaos\source\database\sql\Dialect
{
    /**
     * Escape identifier character.
     *
     * @var array
     */
    protected $_escape = '"';

    /**
     * Meta attribute syntax pattern.
     *
     * Note: by default `'escape'` is false and 'join' is `' '`.
     *
     * @var array
     */
    protected $_meta = [
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
            'classes' => [
                'select'       => 'chaos\source\database\sql\statement\postgresql\Select',
                'insert'       => 'chaos\source\database\sql\statement\postgresql\Insert',
                'update'       => 'chaos\source\database\sql\statement\postgresql\Update',
                'delete'       => 'chaos\source\database\sql\statement\postgresql\Delete',
                'create table' => 'chaos\source\database\sql\statement\CreateTable',
                'drop table'   => 'chaos\source\database\sql\statement\DropTable'
            ],
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

        $column = $this->escape($name);

        if (isset($increment) && $increment) {
            $result = [$column];
            $result[] = 'serial NOT NULL';
        } else {
            $column .= ' ' . $use;

            if ($precision) {
                $precision = $use === 'numeric' ? ",{$precision}" : '';
            }

            if ($length && preg_match('/char|numeric|interval|bit|time/', $use)) {
                $column .= "({$length}{$precision})";
            }

            $result = [$column];

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

        return join(' ', array_filter($result));
    }
}
