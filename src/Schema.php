<?php
namespace Chaos\ORM;

use Iterator;
use InvalidArgumentException;
use DateTime;
use DateTimeZone;
use Lead\Set\Set;
use Chaos\ORM\Collection\Collection;

class Schema
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'relationship'   => 'Chaos\ORM\Relationship',
        'belongsTo'      => 'Chaos\ORM\Relationship\BelongsTo',
        'hasOne'         => 'Chaos\ORM\Relationship\HasOne',
        'hasMany'        => 'Chaos\ORM\Relationship\HasMany',
        'hasManyThrough' => 'Chaos\ORM\Relationship\HasManyThrough'
    ];

    /**
     * The conventions instance.
     *
     * @var object
     */
    protected $_conventions = [];

    /**
     * The source name.
     *
     * @var string
     */
    protected $_source = null;

    /**
     * The fully-namespaced class name of the model object to which this schema is bound.
     *
     * @var string
     */
    protected $_reference = null;

    /**
     * Indicates whether the schema is locked or not.
     *
     * @var boolean
     */
    protected $_locked = true;

    /**
     * The primary key field name.
     *
     * @var string
     */
    protected $_key = null;

    /**
     * The schema meta data.
     *
     * @var array
     */
    protected $_meta = [];

    /**
     * The fields.
     *
     * @var array
     */
    protected $_columns = [];

    /**
     * Casting handlers.
     *
     * @var array
     */
    protected $_handlers = [];

    /**
     * Formatters.
     *
     * @var array
     */
    protected $_formatters = [];

    /**
     * Relations configuration.
     *
     * @var array
     */
    protected $_relations = [];

    /**
     * Loaded relationships.
     *
     * @var array
     */
    protected $_relationships = [];

    /**
     * Configures the meta for use.
     *
     * @param array $config Possible options are:
     *                      - `'source'`      _string_ : The source name (defaults to `null`).
     *                      - `'class'`       _string_ : The fully namespaced document class name (defaults to `null`).
     *                      - `'locked'`      _boolean_: set the ability to dynamically add/remove fields (defaults to `false`).
     *                      - `'key'`         _string_ : The primary key value (defaults to `id`).
     *                      - `'columns'      _array_  : array of field definition where keys are field names and values are arrays
     *                                                   with the following keys. All properties are optionnal except the `'type'`:
     *                                                   - `'type'`      _string_ : the type of the field.
     *                                                   - `'default'`   _mixed_  : the default value (default to '`null`').
     *                                                   - `'null'`      _boolean_: allow null value (default to `'null'`).
     *                                                   - `'length'`    _integer_: the length of the data (default to `'null'`).
     *                                                   - `'precision'` _integer_: the precision (for decimals) (default to `'null'`).
     *                                                   - `'use'`       _string_ : the database type to override the associated type for
     *                                                                              this type (default to `'null'`).
     *                                                   - `'serial'`    _string_ : autoincremented field (default to `'null'`).
     *                                                   - `'primary'`   _boolead_: primary key (default to `'null'`).
     *                                                   - `'unique'`    _boolead_: unique key (default to `'null'`).
     *                                                   - `'reference'` _string_ : foreign key (default to `'null'`).
     *                      - `'meta'`        _array_  : array of meta definitions for the schema. The definitions are related to
     *                                                   the datasource. For the MySQL adapter the following options are available:
     *                                                   - `'charset'`    _string_: the charset value to use for the table.
     *                                                   - `'collate'`    _string_: the collate value to use for the table.
     *                                                   - `'engine'`     _stirng_: the engine value to use for the table.
     *                                                   - `'tablespace'` _string_: the tablespace value to use for the table.
     *                      - `'handlers'`    _array_  : casting handlers.
     *                      - `'conventions'` _object_ : The naming conventions instance.
     *                      - `'classes'`     _array_  : The class dependencies.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'source'      => null,
            'class'       => Document::class,
            'locked'      => true,
            'columns'     => [],
            'meta'        => [],
            'handlers'    => [],
            'conventions' => null,
            'classes'     => $this->_classes
        ];

        $config = Set::extend($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_locked = $config['locked'];
        $this->_meta = $config['meta'];
        $this->_handlers = Set::extend($config['handlers'], $this->_handlers());
        $this->_conventions = $config['conventions'] ?: new Conventions();

        $config += [
            'key' => $this->_conventions->apply('key')
        ];

        $this->_columns = $config['columns'];
        $this->_source = $config['source'];
        $this->_reference = $config['class'];
        $this->_key = $config['key'];

        foreach ($config['columns'] as $key => $value) {
            $this->column($key, $value);
        }

        $handlers = $this->_handlers;

        $this->formatter('array', 'object',   $handlers['array']['object']);
        $this->formatter('array', 'integer',  $handlers['array']['integer']);
        $this->formatter('array', 'float',    $handlers['array']['float']);
        $this->formatter('array', 'decimal',  $handlers['array']['string']);
        $this->formatter('array', 'date',     $handlers['array']['date']);
        $this->formatter('array', 'datetime', $handlers['array']['datetime']);
        $this->formatter('array', 'boolean',  $handlers['array']['boolean']);
        $this->formatter('array', 'null',     $handlers['array']['null']);
        $this->formatter('array', 'json',     $handlers['array']['json']);

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
    }

    /**
     * Gets/sets the source name.
     *
     * @param  string $source The source name (i.e table/collection name) or `null` to get the defined one.
     * @return string
     */
    public function source($source = null)
    {
        if (!func_num_args()) {
            return $this->_source;
        }
        $this->_source = $source;
        return $this;
    }

    /**
     * Gets/sets the attached reference class name.
     *
     * @param  mixed $reference The reference class name to set to none to get the current reference class name.
     * @return mixed           The attached reference class name or `$this`.
     */
    public function reference($reference = null)
    {
        if (!func_num_args()) {
            return $this->_reference;
        }
        $this->_reference = $reference;
        return $this;
    }

    /**
     * Sets the schema lock type. When Locked all extra fields which
     * are not part of the schema should be filtered out before saving.
     *
     * @param  boolean $locked The locked value to set.
     * @return self            Return `$this`.
     */
    public function lock($locked = true)
    {
        $this->_locked = $locked === false ? false : true;
        return $this;
    }

    /**
     * Gets the schema lock type. When Locked all extra fields which
     * are not part of the schema should be filtered out before saving.
     *
     * @return boolean The locked value.
     */
    public function locked()
    {
        return $this->_locked;
    }

    /**
     * Gets/Sets the meta data associated to a field is some exists.
     *
     * @param  string $name The field name. If `null` returns all meta. If it's an array,
     *                      set it as the meta datas.
     * @return mixed        If `$name` is a string, it returns the corresponding value
     *                      otherwise it returns a meta data array or `null` if not found.
     */
    public function meta($name = null, $value = null)
    {
        $num = func_num_args();
        if (!$num) {
            return $this->_meta;
        }
        if (is_array($name)) {
            $this->_meta = $name;
            return $this;
        }
        if ($num === 2) {
            $this->_meta[$name] = $value;
            return $this;
        }
        return isset($this->_meta[$name]) ? $this->_meta[$name] : [];
    }

    /**
     * Gets/sets the primary key field name of the schema.
     *
     * @param  string $key The name or the primary key field name or none to get the defined one.
     * @return string
     */
    public function key($key = null)
    {
        if (!func_num_args()) {
            return $this->_key;
        }
        $this->_key = $key;
        return $this;
    }

    /**
     * Returns all schema column names.
     *
     * @return array An array of column names.
     */
    public function names($rootOnly = false)
    {
        return array_keys($this->columns($rootOnly));
    }

    /**
     * Gets all fields.
     *
     * @param  String basePath The dotted base path to extract fields from.
     * @return array
     */
    public function fields($basePath = '')
    {
        $fields = [];
        foreach ($this->names() as $name) {
            $fields[$name] = null;
        }
        $names = Set::expand($fields);
        if ($basePath) {
            $parts = explode('.', $basePath);
            foreach ($parts as $part) {
                if (!isset($names[$part])) {
                    return [];
                }
                $names = $names[$part];
            }
        }
        return array_keys($names);
    }

    /**
     * Gets all columns (i.e fields + data).
     *
     * @return array
     */
    public function columns($rootOnly = false)
    {
        $columns = [];
        foreach ($this->_columns as $name => $field) {
            if (!empty($field['virtual'])) {
                continue;
            }
            if (!$rootOnly || strpos($name, '.') === false) {
                $columns[$name] = $field;
            }
        }
        return $columns;
    }

    /**
     * Returns the schema default values.
     *
     * @param  array $basePath The basePath to extract default values from.
     * @return mixed           Returns all default values .
     */
    public function defaults($basePath = null)
    {
        $defaults = [];
        foreach ($this->_columns as $key => $value) {
            if ($basePath && strpos($key, $basePath) !== 0) {
                continue;
            }
            $fieldName = $basePath ? substr($key, strlen($basePath) + 1) : $key;
            if (!$fieldName || $fieldName === '*' || strpos($fieldName, '.') !== false) {
                continue;
            }
            if (isset($value['default'])) {
                $defaults[$fieldName] = $value['default'];
            }
        }
        return $defaults;
    }

    /**
     * Returns the type value of a field name.
     *
     * @param  string $name The field name.
     * @return array        The type value or `null` if not found.
     */
    public function type($name)
    {
        if (!$this->has($name)) {
            return;
        }
        $column = $this->column($name);
        return isset($column['type']) ? $column['type'] : null;
    }

    /**
     * Sets a field.
     *
     * @param  string $name The field name.
     * @return object       Returns `$this`.
     */
    public function column($name, $params = [])
    {
        if (func_num_args() === 1) {
            if (!isset($this->_columns[$name])) {
                throw new ORMException("Unexisting column `'{$name}'`");
            }
            return $this->_columns[$name];
        }
        $column = $this->_initColumn($params);

        if ($column['type'] !== 'object' && !$column['array']) {
            $this->_columns[$name] = $column;
            return $this;
        }
        $relationship = $this->_classes['relationship'];

        $this->bind($name, [
            'type'     => $column['array'] ? 'set' : 'entity',
            'relation' => $column['array'] ? 'hasMany' : 'hasOne',
            'to'       => isset($column['class']) ? $column['class'] : Document::class,
            'link'     => $relationship::LINK_EMBEDDED,
            'config'   => isset($column['config']) ? $column['config'] : []
        ]);

        $this->_columns[$name] = $column;
        return $this;
    }

    /**
     * Normalizes a column.
     *
     * @param  array $column A column definition.
     * @return array         A normalized column array.
     */
    protected function _initColumn($column)
    {
        $defaults = [
            'type'  => 'string',
            'array' => false
        ];
        if (is_string($column)) {
            $column = ['type' => $column];
        } elseif (isset($column[0])) {
            $column['type'] = $column[0];
            unset($column[0]);
        }
        $column += $defaults;
        return $column + ['null' => false];
    }

    /**
     * Unset a field/some fields from the schema.
     *
     * @param  string|array $name The field name or an array of field names to remove.
     * @return object             Returns `$this`.
     */
    public function unset($name)
    {
        $names = $name ? (array) $name : [];
        foreach ($names as $name) {
            unset($this->_columns[$name]);
        }
        return $this;
    }

    /**
     * Checks if the schema has a field/some fields.
     *
     * @param  string|array $name The field name or an array of field names to check.
     * @return boolean            Returns `true` if present, `false` otherwise.
     */
    public function has($name)
    {
        if (!is_array($name)) {
            return isset($this->_columns[$name]);
        }
        return array_intersect($name, array_keys($this->_columns)) === $name;
    }

    /**
     * Appends additional fields to the schema. Will overwrite existing fields if a
     * conflicts arise.
     *
     * @param  mixed  $fields The fields array or a schema instance to merge.
     * @param  array  $meta   New meta data.
     * @return object         Returns `$this`.
     */
    public function append($fields)
    {
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $this->column($key, $value);
            }
        } else {
            foreach ($fields->fields() as $name) {
                $this->column($name, $fields->column($name));
            }
        }
        return $this;
    }

    /**
     * Gets all virtual fields.
     *
     * @return array
     */
    public function virtuals($attribute = null)
    {
        $fields = [];
        foreach ($this->_columns as $name => $field) {
            if (empty($field['virtual'])) {
                continue;
            }
            $fields[] = $name;
        }
        return $fields;
    }

    /**
     * Checks if the schema has a field/some virtual fields.
     *
     * @param  string|array $name The field name or an array of field names to check.
     * @return boolean            Returns `true` if present, `false` otherwise.
     */
    public function isVirtual($name)
    {
        if (!is_array($name)) {
            return !empty($this->_columns[$name]['virtual']);
        }
        foreach ($name as $field) {
            if (!$this->isVirtual($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if the schema has a field/some private fields.
     *
     * @param  string|array $name The field name or an array of field names to check.
     * @return boolean            Returns `true` if present, `false` otherwise.
     */
    public function isPrivate($name)
    {
        if (!is_array($name)) {
            return !empty($this->_columns[$name]['private']);
        }
        foreach ($name as $field) {
            if (!$this->isPrivate($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sets a BelongsTo relation.
     *
     * @param  string  $name   The name of the relation (i.e. field name where it will be binded).
     * @param  string  $to     The document class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function belongsTo($name, $to, $config = []) {
        $defaults = [
            'to'       => $to,
            'relation' => 'belongsTo'
        ];
        $config += $defaults;
        return $this->bind($name, $config);
    }

    /**
     * Sets a hasMany relation.
     *
     * @param  string  $name   The name of the relation (i.e. field name where it will be binded).
     * @param  string  $to     The document class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function hasMany($name, $to, $config = []) {
        $defaults = [
            'to'       => $to,
            'relation' => 'hasMany'
        ];
        $config += $defaults;
        return $this->bind($name, $config);
    }

    /**
     * Sets a hasOne relation.
     *
     * @param  string  $name   The name of the relation (i.e. field name where it will be binded).
     * @param  string  $to     The document class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function hasOne($name, $to, $config = []) {
        $defaults = [
            'to'       => $to,
            'relation' => 'hasOne'
        ];
        $config += $defaults;
        return $this->bind($name, $config);
    }

    /**
     * Sets a hasManyThrough relation.
     *
     * @param  string  $name    The name of the relation (i.e. field name where it will be binded).
     * @param  string  $through the relation name to pivot table.
     * @param  string  $using   the target relation name in the through relation.
     * @param  array   $config  The configuration that should be specified in the relationship.
     *                          See the `Relationship` class for more information.
     * @return boolean
     */
    public function hasManyThrough($name, $through, $using, $config = []) {
        $defaults = [
            'through'  => $through,
            'using'    => $using,
            'relation' => 'hasManyThrough'
        ];
        $config += $defaults;
        return $this->bind($name, $config);
    }

    /**
     * Lazy bind a relation.
     *
     * @param  string    $name   The name of the relation (i.e. field name where it will be binded).
     * @param  array     $config The configuration that should be specified in the relationship.
     *                           See the `Chaos\ORM\Relationship` class for more information.
     * @return boolean
     * @throws Exception         Throws a `ORMException` if the config has no type option defined.
     */
    public function bind($name, $config = [])
    {
        $relationship = $this->_classes['relationship'];
        $config += [
            'type' => 'entity',
            'from' => $this->reference(),
            'to'   => null,
            'link' => $relationship::LINK_KEY
        ];
        $config['embedded'] = strncmp($config['link'], 'key', 3) !== 0;

        if (!isset($config['relation']) || !isset($this->_classes[$config['relation']])) {
            throw new ORMException("Unexisting binding relation `{$config['relation']}` for `'{$name}'`.");
        }
        if (!$config['from']) {
            throw new ORMException("Binding requires `'from'` option to be set.");
        }
        if (!$config['to']) {
            if ($config['relation'] !== 'hasManyThrough') {
                throw new ORMException("Binding requires `'to'` option to be set.");
            }
        } elseif (($pos = strrpos('\\', $config['to'])) !== false) {
            $from = $config['from'];
            $config['to'] = substr($from, 0, $pos + 1) . $config['to'];
        }

        $config['array'] = !!preg_match('~Many~', $config['relation']);
        $config['type'] = $config['array'] ? 'set' : $config['type'];

        if ($config['relation'] === 'hasManyThrough') {
            if (!isset($config['through'])) {
                throw new ORMException("Missing through name for `'{$name}'` relation.");
            }
            if (!$this->_relations[$config['through']]) {
                throw new ORMException("Unexisting through relation `'{$config['through']}'`, needed to be created first.");
            }
            $config += ['using' => $this->conventions()->apply('single', $name)];
            $config['type'] = 'through';
            $this->_relations[$config['through']]['junction'] = true;
        } elseif ($config['relation'] === 'belongsTo' && $config['link'] === $relationship::LINK_KEY) {
            $fieldName = $this->conventions()->apply('reference', $name);
            $this->column($fieldName, ['type' => 'id', 'array' => false, 'null' => true]);
        } elseif ($config['relation'] === 'hasMany' && $config['link'] === $relationship::LINK_KEY_LIST) {
            $fieldName = $this->conventions()->apply('references', $name);
            $this->column($fieldName, ['type' => 'id', 'array' => true, 'null' => true]);
        }

        if (isset($this->_relations[$name]['junction'])) {
            $this->_relations[$name] = $config + ['junction' => $this->_relations[$name]['junction']];
        } else {
            $this->_relations[$name] = $config;
        }
        $this->_relationships[$name] = null;
        return true;
    }

    /**
     * Unbinds a relation.
     *
     * @param string $name The name of the relation to unbind.
     */
    public function unbind($name)
    {
        if (!isset($this->_relations[$name])) {
            return;
        }
        unset($this->_relations[$name]);
        unset($this->_relationships[$name]);
    }

    /**
     * Returns a relationship instance.
     *
     * @param  string $name The name of a relation.
     * @return object       Returns a relationship intance or `null` if it doesn't exists.
     */
    public function relation($name)
    {
        if (isset($this->_relationships[$name])) {
            return $this->_relationships[$name];
        }
        if (!isset($this->_relations[$name])) {
            throw new ORMException("Relationship `{$name}` not found.");
        }
        $config = $this->_relations[$name];
        $relationship = $config['relation'];
        unset($config['relation']);

        $relation = $this->_classes[$relationship];
        return $this->_relationships[$name] = new $relation($config + [
            'name'        => $name,
            'conventions' => $this->_conventions
        ]);
    }

    /**
     * Returns an array of external relation names.
     *
     * @param  boolean $embedded Include or not embedded relations.
     * @return array             Returns an array of relation names.
     */
    public function relations($embedded = false)
    {
        $result = [];
        foreach ($this->_relations as $field => $config) {
            if (!$config['embedded'] || $embedded) {
                $result[] = $field;
            }
        }
        return $result;
    }

    /**
     * Checks if a relation exists.
     *
     * @param  string  $name     The name of a relation.
     * @param  boolean $embedded Check for embedded relations or not. `null` means indifferent, `true` means embedded only
     *                           and `false` mean external only.
     * @return boolean           Returns `true` if the relation exists, `false` otherwise.
     */
    public function hasRelation($name, $embedded = null)
    {
        if (!isset($this->_relations[$name])) {
            return false;
        }
        $relation = $this->_relations[$name];
        if ($embedded === null) {
            return true;
        }
        return $embedded === $relation['embedded'];
    }

    /**
     * Eager loads relations.
     *
     * @param array $collection The collection to extend.
     * @param array $relations  The relations to eager load.
     * @param array $options    The fetching options.
     */
    public function embed(&$collection, $relations, $options = [])
    {
        $expanded = [];
        $relations = $this->expand($relations);
        $tree = $this->treeify($relations);

        $habtm = [];

        foreach ($tree as $name => $subtree) {
            $rel = $this->relation($name);
            if ($rel->type() === 'hasManyThrough') {
                $habtm[] = $name;
                continue;
            }

            $to = $rel->to();
            $query = empty($relations[$name]) ? [] : $relations[$name];
            if (is_callable($query)) {
                $options['query']['handler'] = $query;
            } else {
                $options['query'] = $query;
            }
            $related = $rel->embed($collection, $options);

            $subrelations = [];
            foreach ($relations as $path => $value) {
                if (preg_match('~^'.$name.'\.(.*)$~', $path, $matches)) {
                    $subrelations[$matches[1]] = $value;
                }
            }
            if ($subrelations) {
                $to::definition()->embed($related, $subrelations, $options);
            }
        }

        foreach ($habtm as $name) {
            $rel = $this->relation($name);
            $related = $rel->embed($collection, $options);
        }
    }

    /**
     * Expands all `'hasManyThrough'` relations into their full path.
     *
     * @param  array $relations The relations to eager load.
     * @return array            The relations with expanded `'hasManyThrough'` relations.
     */
    public function expand($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        $relations = Set::normalize($relations);

        foreach ($relations as $path => $value) {
            $num = strpos($path, '.');
            $name = $num !== false ? substr($path, 0, $num) : $path;
            $rel = $this->relation($name);
            if ($rel->type() !== 'hasManyThrough') {
                continue;
            }
            $relPath = $rel->through() . '.' . $rel->using() . ($num !== false ? '.' . substr($path, $num + 1) : '');
            if (!isset($relations[$relPath])) {
                $relations[$relPath] = $relations[$path];
            }
        }
        return $relations;
    }

    /**
     * Returns a nested tree representation of `'embed'` option.
     *
     * @return array The corresponding nested tree representation.
     */
    public function treeify($embed)
    {
        if (!$embed) {
            return [];
        }
        $embed = Set::expand(Set::normalize((array) $embed), ['affix' => 'embed']);

        $result = [];
        foreach ($embed as $relName => $value) {
            if (!isset($this->_relations[$relName])) {
                continue;
            }
            if ($this->_relations[$relName]['relation'] === 'hasManyThrough') {
                $rel = $this->relation($relName);
                if (!isset($result[$rel->through()]['embed'][$rel->using()])) {
                    $result[$rel->through()]['embed'][$rel->using()] = $value;
                }
            }
            $result[$relName] = $value;
        }
        return $result;
    }

    /**
     * Cast data according to the schema definition.
     *
     * @param  string $field   The field name.
     * @param  array  $data    Some data to cast.
     * @param  array  $options Options for the casting.
     * @return object          The casted data.
     */
    public function cast($field = null, $data = [], $options = [])
    {
        $defaults = [
            'parent'    => null,
            'basePath'  => null,
            'exists'    => null,
            'defaults'  => true
        ];
        $options += $defaults;

        $options['class'] = $this->reference();

        if ($field !== null) {
            $name = $options['basePath'] ? $options['basePath'] . '.' . $field : (string) $field;
        } else {
            $name = $options['basePath'];
        }

        if ($name === null) {
            return $this->_cast($name, $data, $options);
        }

        foreach([$name, preg_replace('~[^.]*$~', '*', $name, 1)] as $entry) {
            if (isset($this->_relations[$entry])) {
                return $this->_relationCast($field, $entry, $data, $options);
            }
            if (isset($this->_columns[$entry])) {
                return $this->_columnCast($field, $entry, $data, $options);
            }
        }

        if ($this->locked()) {
            throw new ORMException("Missing schema definition for field: `" . $name . "`.");
        }

        if (is_array($data)) {
            if (isset($data[0])) {
                return $this->_castArray($name, $data, $options);
            } else {
                $options['class'] = Document::class;
                return $this->_cast($name, $data, $options);
            }
        }

        return $data;
    }

    /**
     * Casting helper for relations.
     *
     * @param  string $field      The field name to cast.
     * @param  string $name       The full field name to cast.
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    protected function _relationCast($field, $name, $data, $options)
    {
        $options = $this->_relations[$name] + $options;
        $options['basePath'] = $options['embedded'] ? $name : null;

        if ($options['relation'] !== 'hasManyThrough') {
            $options['class'] = $options['to'];
        } else {
            $through = $this->relation($name);
            $options['class'] = $through->to();
        }
        if ($field) {
            return $options['array'] ? $this->_castArray($name, $data, $options) : $this->_cast($name, $data, $options);
        }
        if (!isset($this->_columns[$name])) {
            return $data;
        }
        $column = $this->_columns[$name];
        return $this->convert('cast', $column['type'], $data, $column);
    }

    /**
     * Casting helper for columns.
     *
     * @param  string $field      The field name to cast.
     * @param  string $name       The full field name to cast.
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    protected function _columnCast($field, $name, $data, $options)
    {
        $column = $this->_columns[$name];
        $options = $column + $options;
        if (!empty($options['setter'])) {
            $data = $options['setter']($options['parent'], $data, $name);
        }
        if ($options['array'] && $field) {
            return $this->_castArray($name, $data, $options);
        }
        return $this->convert('cast', $column['type'], $data, $column);
    }

    /**
     * Casting helper for entities.
     *
     * @param  string $name       The field name to cast.
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    public function _cast($name, $data, $options)
    {
        if ($data === null) {
            return;
        }
        if ($data instanceof Document) {
            return $data;
        }

        $class = ltrim($options['class'], '\\');

        $config = [
            'schema'    => $class === Document::class ? $this : null,
            'basePath'  => $options['basePath'],
            'exists'    => $options['exists'],
            'defaults'  => $options['defaults']
        ];
        if (isset($options['config'])){
            $config = Set::extend($config, $options['config']);
        }

        $column = isset($this->_columns[$name]) ? $this->_columns[$name] : [];
        if (!empty($column['format'])) {
            $data = $this->convert('cast', $column['format'], $data, $column);
        }

        return $class::create($data ? $data : [], $config);
    }

    /**
     * Casting helper for arrays.
     *
     * @param  string $name       The field name to cast.
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    public function _castArray($name, $data, $options)
    {
        $options['type'] = isset($options['relation']) && $options['relation'] === 'hasManyThrough' ? 'through' : 'set';
        $class = ltrim($options['class'], '\\');
        $classes = $class::classes();
        $collection = $classes[$options['type']];

        if ($data instanceof $collection) {
            return $data;
        }

        $isThrough = $options['type'] === 'through';

        $isDocument = $class === Document::class;

        $config = [
            'schema'    => $isDocument ? $this : $class::definition(),
            'basePath'  => $isDocument ? $name : null,
            'meta'      => isset($options['meta']) ? $options['meta'] : [],
            'exists'    => $options['exists'],
            'defaults'  => $options['defaults']
        ];
        if (isset($options['config'])){
            $config = Set::extend($config, $options['config']);
        }

        $column = isset($this->_columns[$name]) ? $this->_columns[$name] : [];
        if (!empty($column['format']) && $data !== null) {
            $type = $column['format'];
            $data = $this->convert('cast', $type, $data, $column);
        }

        if ($isThrough) {
            $config['parent'] = $options['parent'];
            $config['through'] = $options['through'];
            $config['using'] = $options['using'];
            $config['data'] = $data;
        } else {
            $config['data'] = $data ?: [];
        }
        return new $collection($config);
    }

    /**
     * Return default casting handlers.
     *
     * @return array
     */
    protected function _handlers()
    {
        return [
            'array' => [
                'object' => function($value, $options = []) {
                    return $value->to('array', $options);
                },
                'string' => function($value, $options = []) {
                    return (string) $value;
                },
                'integer' => function($value, $options = []) {
                    return (int) $value;
                },
                'float' => function($value, $options = []) {
                    return (float) $value;
                },
                'date' => function($value, $options = []) {
                    return $this->convert('array', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime' => function($value, $options = []) {
                    $options += ['format' => 'Y-m-d H:i:s'];
                    $format = $options['format'];
                    if ($value instanceof DateTime) {
                        return $value->format($format);
                    }
                    return date($format, is_numeric($value) ? $value : strtotime($value));
                },
                'boolean' => function($value, $options = []) {
                    return $value;
                },
                'null' => function($value, $options = []) {
                    return;
                },
                'json' => function($value, $options = []) {
                    return is_array($value) ? $value : $value->to('array', $options);
                }
            ],
            'cast' => [
                'object' => function($value, $options) {
                    return is_array($value) ? new Document(['data' => $value]) : $value;
                },
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
                    $date = $this->convert('cast', 'datetime', $value, ['format' => 'Y-m-d']);
                    $date->setTime(0, 0, 0);
                    return $date;
                },
                'datetime'    => function($value, $options = []) {
                    $options += ['format' => 'Y-m-d H:i:s'];
                    if ($value instanceof DateTime) {
                        return $value;
                    }
                    $timestamp = is_numeric($value) ? $value : strtotime($value);
                    if ($timestamp < 0 || $timestamp === false) {
                        $timestamp = 0;
                    }
                    return DateTime::createFromFormat($options['format'], date($options['format'], $timestamp), new DateTimeZone('UTC'));
                },
                'null'    => function($value, $options = []) {
                    return null;
                },
                'json' => function($value, $options = []) {
                    return is_string($value) ? json_decode($value, true) : $value;
                }
            ]
        ];
    }

    /**
     * Formats a value according to a field definition.
     *
     * @param   string $mode The format mode (i.e. `'array'` or `'datasource'`).
     * @param   string $name The field name.
     * @param   mixed  $data The data to format.
     * @return  mixed        The formated value.
     */
    public function format($mode, $name, $data)
    {
        if ($mode === 'cast') {
            throw new InvalidArgumentException("Use `Schema::cast()` to perform casting.");
        }
        if (!isset($this->_columns[$name])) {
            // Formatting non defined columns or relations doesn't make sense, bailing out.
            return $this->convert($mode, '_default_', $data, []);
        }
        $column = $this->_columns[$name];
        $type = $data === null ? 'null' : $this->type($name);

        if (!$column['array']) {
            $data = $this->convert($mode, $type, $data, $column);
        } else {
            $data = Collection::format($mode, $data);
        }
        if (!empty($column['format'])) {
            $data = $this->convert($mode, $column['format'], $data, $column);
        }
        return $data;
    }

    /**
     * Formats a value according to its type.
     *
     * @param   string $mode    The format mode (i.e. `'cast'` or `'datasource'`).
     * @param   string $type    The field name.
     * @param   mixed  $data    The value to format.
     * @param   mixed  $options The options array to pass the the formatter handler.
     * @return  mixed           The formated value.
     */
    public function convert($mode, $type, $data, $options = [])
    {
        $formatter = null;
        $type = $data === null ? 'null' : $type;
        if (isset($this->_formatters[$mode][$type])) {
            $formatter = $this->_formatters[$mode][$type];
        } elseif (isset($this->_formatters[$mode]['_default_'])) {
            $formatter = $this->_formatters[$mode]['_default_'];
        }
        return $formatter ? $formatter($data, $options) : $data;
    }

    /**
     * Gets/sets a formatter handler.
     *
     * @param  string   $mode          The formatting mode.
     * @param  string   $type          The field type name.
     * @param  callable $handler       The formatter handler to set or none to get it.
     * @return object                  Returns `$this` on set and the formatter handler on get.
     */
    public function formatter($mode, $type, $handler = null)
    {
        if (func_num_args() === 2) {
            return isset($this->_formatters[$mode][$type]) ? $this->_formatters[$mode][$type] : $this->_formatters[$mode]['_default_'];
        }
        $this->_formatters[$mode][$type] = $handler;
        return $this;
    }

    /**
     * Gets/sets all formatters.
     *
     * @param  array $formatters The formatters to set or none to get them.
     * @return mixed             Returns `$this` on set and the formatters array on get.
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
     * Gets/sets the conventions object to which this schema is bound.
     *
     * @param  object $conventions The conventions instance to set or none to get it.
     * @return object              Returns `$this` on set and the conventions instance on get.
     */
    public function conventions($conventions = null)
    {
        if (func_num_args()) {
            $this->_conventions = $conventions;
            return $this;
        }
        return $this->_conventions;
    }

    /**
     * Inserts and/or updates an entity or a collection of entities and its direct relationship data.
     *
     * @param object   $instance  The entity or collection instance to save.
     * @param array    $options   Options:
     *                            - `'whitelist'` _array_  : An array of fields that are allowed to be saved to this record.
     *                            - `'locked'`    _boolean_: Lock data to the schema fields.
     *                            - `'embed'`     _array_  : List of relations to save.
     * @return boolean            Returns `true` on a successful save operation, `false` otherwise.
     */
    public function save($instance, $options = [])
    {
        $defaults = [
            'whitelist' => null,
            'locked'    => $this->locked(),
            'embed'     => false
        ];
        $options += $defaults;

        $options['validate'] = false;

        if ($options['embed'] === true) {
            $options['embed'] = $instance->hierarchy();
        }

        $options['embed'] = $this->treeify($options['embed']);

        if (!$this->saveRelation($instance, 'belongsTo', $options)) {
            return false;
        }

        $success = $this->persist($instance, $options);

        return $success && $this->saveRelation($instance, ['hasMany', 'hasOne'], $options);
    }

    /**
     * Inserts and/or updates an entity or a collection of entities.
     *
     * @param object   $instance  The entity or collection instance to save.
     * @param array    $options   Options:
     *                            - `'whitelist'` _array_  : An array of fields that are allowed to be saved to this record.
     *                            - `'locked'`    _boolean_: Lock data to the schema fields.
     *                            - `'embed'`     _array_  : List of relations to save.
     * @return boolean            Returns `true` on a successful save operation, `false` otherwise.
     */
    public function persist($instance, $options = [])
    {
        $defaults = [
            'whitelist' => null,
            'locked' => $this->locked()
        ];
        $options += $defaults;

        if (!$options['whitelist']) {
            $whitelist = $options['locked'] ? $this->fields() : [];
        } else if ($options['locked']) {
            $whitelist = array_intersect($this->fields(), $options['whitelist']);
        } else {
            $whitelist = $options['whitelist'];
        }

        $collection = $instance instanceof Document ? [$instance] : $instance;

        $inserts = [];
        $updates = [];

        $filter = function($entity) use ($whitelist) {
            $fields = array_diff($whitelist ? $whitelist : array_keys($entity->get()), $this->relations());
            $values = [];
            foreach ($fields as $field) {
                if ($entity->has($field) && !$this->isVirtual($field)) {
                    $values[$field] = $entity->get($field);
                }
            }
            return $values;
        };

        foreach ($collection as $entity) {
            $entity->sync();
            if (!$entity->exists()) {
                $inserts[] = $entity;
            } elseif ($entity->modified($options)) {
                $updates[] = $entity;
            }
        }
        return $this->bulkInsert($inserts, $filter) && $this->bulkUpdate($updates, $filter);
    }

    /**
     * Save data related to relations.
     *
     * @param  object  $instance The entity instance.
     * @param  array   $types    Type of relations to save.
     * @param  array   $options  Options array.
     * @return boolean           Returns `true` on a successful save operation, `false` on failure.
     */
    public function saveRelation($instance, $types, $options = [])
    {
        $defaults = ['embed' => []];
        $options += $defaults;
        $types = (array) $types;

        $collection = $instance instanceof Document ? [$instance] : $instance;

        $success = true;
        foreach ($collection as $entity) {
            foreach ($types as $type) {
                foreach ($options['embed'] as $relName => $value) {
                    if (!($rel = $this->relation($relName)) || $rel->type() !== $type) {
                        continue;
                    }
                    $success = $success && $rel->save($entity,  $value ? $value + $options : $options);
                }
            }
        }
        return $success;
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @param  object  $instance The entity or collection instance to save.
     * @return boolan            Return `true` on success `false` otherwise.
     */
    public function delete($instance)
    {
        $collection = $instance instanceof Document ? [$instance] : $instance;

        $key = $this->key();
        if (!$key) {
          throw new ORMException("No primary key has been defined for `" + instance.self() + "`'s schema.");
        }

        $keys = [];

        foreach ($collection as $entity) {
            $entity->sync();
            if ($entity->exists()) {
                $keys[] = $entity->id();
            }
        }

        if (!$keys) {
            return true;
        }

        if (!$this->truncate([$key => (count($keys) ===1 ? $keys[0] : $keys)])) {
            return false;
        }

        foreach ($collection as $entity) {
            $entity->amend([], ['exists' => false]);
        }

        return true;
    }

    /**
     * Returns a query to retrieve data from the connected data source.
     *
     * @param  array  $options Query options.
     * @return object          An instance of `Query`.
     */
    public function query($options = [])
    {
        throw new ORMException("Missing `query()` implementation for this schema.");
    }

    /**
     * Bulk inserts
     *
     * @param  array   $inserts An array of entities to insert.
     * @param  Closure $filter  The filter handler for which extract entities values for the insertion.
     * @return boolean          Returns `true` if insert operations succeeded, `false` otherwise.
     */
    public function bulkInsert($inserts, $filter)
    {
        throw new ORMException("Missing `bulkInsert()` implementation for `{$this->_reference}`'s schema.");
    }

    /**
     * Bulk updates
     *
     * @param  array   $updates An array of entities to update.
     * @param  Closure $filter  The filter handler for which extract entities values to update.
     * @return boolean          Returns `true` if update operations succeeded, `false` otherwise.
     */
    public function bulkUpdate($updates, $filter)
    {
        throw new ORMException("Missing `bulkUpdate()` implementation for `{$this->_reference}`'s schema.");
    }

    /**
     * Removes multiple documents or records based on a given set of criteria. **WARNING**: If no
     * criteria are specified, or if the criteria (`$conditions`) is an empty value (i.e. an empty
     * array or `null`), all the data in the backend data source (i.e. table or collection) _will_
     * be deleted.
     *
     * @param mixed    $conditions An array of key/value pairs representing the scope of the records or
     *                             documents to be deleted.
     * @return boolean             Returns `true` if the remove operation succeeded, otherwise `false`.
     */
    public function truncate($conditions = [])
    {
        throw new ORMException("Missing `truncate()` implementation for `{$this->_reference}`'s schema.");
    }

}
