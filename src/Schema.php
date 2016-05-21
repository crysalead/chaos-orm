<?php
namespace Chaos;

use Iterator;
use DateTime;
use Lead\Set\Set;
use Chaos\Model;
use Chaos\Document;

class Schema
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'set'            => 'Chaos\Collection\Collection',
        'through'        => 'Chaos\Collection\Through',
        'relationship'   => 'Chaos\Relationship',
        'belongsTo'      => 'Chaos\Relationship\BelongsTo',
        'hasOne'         => 'Chaos\Relationship\HasOne',
        'hasMany'        => 'Chaos\Relationship\HasMany',
        'hasManyThrough' => 'Chaos\Relationship\HasManyThrough'
    ];

    /**
     * The connection instance.
     *
     * @var object
     */
    protected $_connection = null;

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
    protected $_model = null;

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
    protected $_fields = [];

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
     *                      - `'connection'`  _object_ : The connection instance (defaults to `null`).
     *                      - `'source'`      _string_ : The source name (defaults to `null`).
     *                      - `'model'`       _string_ : The fully namespaced model class name (defaults to `null`).
     *                      - `'locked'`      _boolean_: set the ability to dynamically add/remove fields (defaults to `false`).
     *                      - `'key'`         _string_ : The primary key value (defaults to `id`).
     *                      - `'fields'`      _array_  : array of field definition where keys are field names and values are arrays
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
            'connection'   => null,
            'source'       => null,
            'model'        => Document::class,
            'locked'       => true,
            'fields'       => [],
            'meta'         => [],
            'handlers'     => [],
            'conventions'  => null,
            'classes'      => $this->_classes
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_connection = $config['connection'];
        $this->_locked = $config['locked'];
        $this->_meta = $config['meta'];
        $this->_handlers = Set::merge($config['handlers'], $this->_handlers());
        $this->_conventions = $config['conventions'] ?: new Conventions();

        $config += [
            'key' => $this->_conventions->apply('key')
        ];

        $this->_fields = $config['fields'];
        $this->_source = $config['source'];
        $this->_model = $config['model'];
        $this->_key = $config['key'];

        foreach ($config['fields'] as $key => $value) {
            $this->_fields[$key] = $this->_initField($value);
        }

        if ($this->_connection) {
            $this->_formatters = $this->_connection->formatters();
        }

        $handlers = $this->_handlers;

        $this->formatter('array', 'id',        $handlers['array']['integer']);
        $this->formatter('array', 'serial',    $handlers['array']['integer']);
        $this->formatter('array', 'integer',   $handlers['array']['integer']);
        $this->formatter('array', 'float',     $handlers['array']['float']);
        $this->formatter('array', 'decimal',   $handlers['array']['float']);
        $this->formatter('array', 'date',      $handlers['array']['date']);
        $this->formatter('array', 'datetime',  $handlers['array']['date']);
        $this->formatter('array', 'boolean',   $handlers['array']['boolean']);
        $this->formatter('array', 'null',      $handlers['array']['null']);
        $this->formatter('array', '_default_', $handlers['array']['string']);

        $this->formatter('cast', 'integer',  $handlers['cast']['integer']);
        $this->formatter('cast', 'float',    $handlers['cast']['float']);
        $this->formatter('cast', 'decimal',  $handlers['cast']['float']);
        $this->formatter('cast', 'date',     $handlers['cast']['datetime']);
        $this->formatter('cast', 'datetime', $handlers['cast']['datetime']);
        $this->formatter('cast', 'boolean',  $handlers['cast']['boolean']);
        $this->formatter('cast', 'null',     $handlers['cast']['null']);
        $this->formatter('cast', 'string',   $handlers['cast']['string']);

        if ($this->_connection) {
            $this->_formatters = Set::merge($this->_formatters, $this->_connection->formatters());
        }
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    Returns a connection instance.
     * @throws Exception Throws a `ChaosException` if a connection isn't set.
     */
    public function connection($connection = null)
    {
        if (func_num_args()) {
            $this->_connection = $connection;
            return $this;
        }
        if (!$this->_connection) {
            throw new ChaosException("Error, missing connection for this schema.");
        }
        return $this->_connection;
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
     * Gets/sets the attached model class name.
     *
     * @param  mixed $model The model class name to set to none to get the current model class name.
     * @return mixed        The attached model class name or `$this`.
     */
    public function model($model = null)
    {
        if (!func_num_args()) {
            return $this->_model;
        }
        $this->_model = $model;
        return $this;
    }

    /**
     * Gets/sets the schema lock type. When Locked all extra fields which
     * are not part of the schema should be filtered out before saving.
     *
     * @param  boolean $locked The locked value to set to none to get the current lock value.
     * @return mixed           A boolean value or `$this`.
     */
    public function locked($locked = null)
    {
        if (!func_num_args()) {
            return $this->_locked;
        }
        $this->_locked = $locked;
        return $this;
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
     * Returns all schema field names.
     *
     * @return array An array of field names.
     */
    public function names()
    {
        return array_keys($this->_fields);
    }

    /**
     * Gets all fields.
     *
     * @return array
     */
    public function fields()
    {
        $fields = [];
        foreach ($this->_fields as $name => $field) {
            if (!empty($field['virtual'])) {
                continue;
            }
            $fields[] = $name;
        }
        return $fields;
    }


    /**
     * Gets all columns (i.e fields + data).
     *
     * @return array
     */
    public function columns()
    {
        $columns = [];
        foreach ($this->_fields as $name => $field) {
            if (!empty($field['virtual'])) {
                continue;
            }
            $columns[$name] = $field;
        }
        return $columns;
    }

    /**
     * Returns a schema field attribute.
     *
     * @param  string $name      A field name.
     * @param  mixed  $attribute An attribute name. If `null` returns all attributes.
     * @return mixed
     */
    public function field($name, $attribute = null)
    {
        if (!isset($this->_fields[$name])) {
            return;
        }
        $field = $this->_fields[$name];

        if ($attribute) {
            return isset($field[$attribute]) ? $field[$attribute] : null;
        }
        return $field;
    }

    /**
     * Returns the schema default values.
     *
     * @param  array $name An optionnal field name.
     * @return mixed       Returns all default values or a specific one if `$name` is set.
     */
    public function defaults($name = null)
    {
        if ($name) {
            return isset($this->_fields[$name]['default']) ? $this->_fields[$name]['default'] : null;
        }
        $defaults = [];
        foreach ($this->_fields as $key => $value) {
            if (isset($value['default'])) {
                $defaults[$key] = $value['default'];
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
        return $this->field($name, 'type');
    }

    /**
     * Sets a field.
     *
     * @param  string $name The field name.
     * @return object       Returns `$this`.
     */
    public function set($name, $params = [])
    {
        $field = $this->_initField($params);

        if ($field['type'] !== 'object') {
            $this->_fields[$name] = $field;
            return $this;
        }
        $relationship = $this->_classes['relationship'];

        $this->bind($name, [
            'type'     => $field['array'] ? 'set' : 'entity',
            'relation' => $field['array'] ? 'hasMany' : 'hasOne',
            'to'       => isset($field['model']) ? $field['model'] : $this->model(),
            'link'     => $relationship::LINK_EMBEDDED
        ]);

        $this->_fields[$name] = $field;
        return $this;
    }

    /**
     * Normalizes a field.
     *
     * @param  array $field A field array.
     * @return array        A normalized field array.
     */
    protected function _initField($field)
    {
        $defaults = [
            'type'  => 'string',
            'array' => false
        ];
        if (is_string($field)) {
            $field = ['type' => $field];
        } elseif (isset($field[0])) {
            $field['type'] = $field[0];
            unset($field[0]);
        }
        $field += $defaults;
        return $field + ['null' => ($field['type'] !== 'serial')];
    }

    /**
     * Removes a field/some fields from the schema.
     *
     * @param  string|array $name The field name or an array of field names to remove.
     * @return object             Returns `$this`.
     */
    public function remove($name)
    {
        $names = $name ? (array) $name : [];
        foreach ($names as $name) {
            unset($this->_fields[$name]);
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
            return isset($this->_fields[$name]);
        }
        return array_intersect($name, array_keys($this->_fields)) === $name;
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
                $this->_fields[$key] = $this->_initField($value);
            }
        } else {
            foreach ($fields->fields() as $name) {
                $this->_fields[$name] = $fields->field($name);
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
        foreach ($this->_fields as $name => $field) {
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
            return !empty($this->_fields[$name]['virtual']);
        }
        foreach ($name as $field) {
            if (!$this->isVirtual($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sets a BelongsTo relation.
     *
     * @param  string  $name   The name of the relation (i.e. field name where it will be binded).
     * @param  string  $to     The model class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function belongsTo($name, $to, $config) {
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
     * @param  string  $to     The model class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function hasMany($name, $to, $config) {
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
     * @param  string  $to     The model class name to bind.
     * @param  array   $config The configuration that should be specified in the relationship.
     *                         See the `Relationship` class for more information.
     * @return boolean
     */
    public function hasOne($name, $to, $config) {
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
     *                           See the `Chaos\Relationship` class for more information.
     * @return boolean
     * @throws Exception         Throws a `ChaosException` if the config has no type option defined.
     */
    public function bind($name, $config = [])
    {
        $relationship = $this->_classes['relationship'];

        $config += [
            'type' => 'entity',
            'from' => $this->model(),
            'to'   => null,
            'link' => $relationship::LINK_KEY
        ];
        $config['embedded'] = strncmp($config['link'], 'key', 3) !== 0;

        if (!isset($config['relation']) || !isset($this->_classes[$config['relation']])) {
            throw new ChaosException("Unexisting binding relation `{$config['relation']}` for `'{$name}'`.");
        }
        if (!$config['from']) {
            throw new ChaosException("Binding requires `'from'` option to be set.");
        }
        if (!$config['to']) {
            if ($config['relation'] !== 'hasManyThrough') {
                throw new ChaosException("Binding requires `'to'` option to be set.");
            }
        } elseif (($pos = strrpos('\\', $config['to'])) !== false) {
            $from = $config['from'];
            $config['to'] = substr($from, 0, $pos + 1) . $config['to'];
        }

        $config['array'] = !!preg_match('~Many~', $config['relation']);
        $config['type'] = $config['array'] ? 'set' : $config['type'];

        if ($config['relation'] === 'hasManyThrough') {
            if (!isset($config['through'])) {
                throw new ChaosException("Missing `'through'` relation name.");
            }
            $config += ['using' => $this->_conventions->apply('single', $name)];
            $config['type'] = 'through';
            $this->_relations[$config['through']]['junction'] = true;
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
            throw new ChaosException("Relationship `{$name}` not found.");
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
     * @param  string  $name The name of a relation.
     * @return boolean       Returns `true` if the relation exists, `false` otherwise.
     */
    public function hasRelation($name)
    {
        return isset($this->_relations[$name]);
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
        $embed = Set::expand(array_fill_keys(array_keys(Set::normalize((array) $embed)), null));

        $result = [];
        foreach ($embed as $relName => $value) {
            if (!isset($this->_relations[$relName])) {
                continue;
            }
            if ($this->_relations[$relName]['relation'] === 'hasManyThrough') {
                $rel = $this->relation($relName);
                $result[$rel->through()] = [$rel->using() => $value];
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
            'collector' => null,
            'parent'    => null,
            'rootPath'  => null,
            'exists'    => false
        ];
        $options += $defaults;

        $options['model'] = $this->model();
        $options['schema'] = $this;

        if ($field) {
            $name = $options['rootPath'] ? $options['rootPath'] . '.' . $field : $field;
        } else {
            $name = $options['rootPath'];
        }

        if ($name === null) {
            return $this->_cast($data, $options);
        }

        if (isset($this->_relations[$name])) {
            $options = $this->_relations[$name] + $options;
            $options['rootPath'] = $options['embedded'] ? $name : null;

            if ($options['relation'] !== 'hasManyThrough') {
                $options['model'] = $options['to'];
            } else {
                $through = $this->relation($name);
                $options['model'] = $through->to();
            }

            if ($options['array'] && $field) {
                return $this->_castArray($name, $data, $options);
            }
            return $this->_cast($data, $options);
        }

        if (isset($this->_fields[$name])) {
            $options = $this->_fields[$name] + $options;
            if (!empty($options['setter'])) {
                $data = $options['setter']($options['parent'], $data, $name);
            }
            if ($options['array'] && $field) {
                return $this->_castArray($name, $data, $options);
            }
            return $this->format('cast', $name, $data);
        }

        if ($this->locked()) {
            throw new ChaosException("Missing schema definition for field: `" . $name . "`.");
        }
        if (is_array($data)) {
            if ($data === array_values($data)) {
                return $this->_castArray($name, $data, $options);
            } else {
                return $this->_cast($data, $options);
            }
        }

        return $data;
    }

    /**
     * Casting helper for entities.
     *
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    public function _cast($data, $options)
    {
        if ($data instanceof Document) {
            $data->collector($options['collector']);
            $data->parent($options['parent']);
            $data->rootPath($options['rootPath']);
            return $data;
        }
        $options['type'] = 'entity';
        $model = $options['model'];
        return $model::create($data, $options);
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
        $collection = $this->_classes[$options['type']];
        if ($data instanceof $collection) {
            $data->collector($options['collector']);
            $data->parent($options['parent']);
            $data->rootPath($options['rootPath']);
            return $data;
        }
        $model = $options['model'];
        return $model::create($data, $options);
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
                }
            ],
            'cast' => [
                'string' => function($value, $options = []) {
                    return (string) $value;
                },
                'integer' => function($value, $options = []) {
                    return (integer) $value;
                },
                'float' => function($value, $options = []) {
                    return (float) $value;
                },
                'decimal' => function($value, $options = []) {
                    $options += ['precision' => 2];
                    return (float) number_format($value, $options['precision']);
                },
                'boolean' => function($value, $options = []) {
                    return !!$value;
                },
                'date' => function($value, $options = []) {
                    return $this->format('cast', 'datetime', $value, ['format' => 'Y-m-d']);
                },
                'datetime' => function($value, $options = []) {
                    $options += ['format' => 'Y-m-d H:i:s'];
                    if (is_numeric($value)) {
                        return new DateTime('@' . $value);
                    }
                    if ($value instanceof DateTime) {
                        return $value;
                    }
                    return DateTime::createFromFormat($options['format'], date($options['format'], strtotime($value)));
                },
                'null' => function($value, $options = []) {
                    return null;
                }
            ]
        ];
    }

    /**
     * Formats a value according to a field definition.
     *
     * @param   string $mode    The format mode (i.e. `'cast'` or `'datasource'`).
     * @param   string $name    The field name.
     * @param   mixed  $value   The value to format.
     * @param   mixed  $options The options array to pass the the formatter handler.
     * @return  mixed           The formated value.
     */
    public function format($mode, $name, $value, $options = [])
    {
        $type = $value === null ? 'null' : $this->type($name);

        $formatter = null;

        if (isset($this->_formatters[$mode][$type])) {
            $formatter = $this->_formatters[$mode][$type];
        } elseif (isset($this->_formatters[$mode]['_default_'])) {
            $formatter = $this->_formatters[$mode]['_default_'];
        }
        return $formatter ? $formatter($value, $options) : $value;
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
     * Returns a query to retrieve data from the connected data source.
     *
     * @param  array  $options Query options.
     * @return object          An instance of `Query`.
     */
    public function query($options = [])
    {

        throw new ChaosException("Missing `query()` implementation for this schema.");
    }

    /**
     * Inserts and/or updates an entity and its direct relationship data in the datasource.
     *
     * @param object   $entity  The entity instance to save.
     * @param array    $options Options.
     * @return boolean          Returns `true` on a successful save operation, `false` otherwise.
     */
    public function save($entity, $options = [])
    {
        throw new ChaosException("Missing `save()` implementation for `{$this->_model}`'s schema.");
    }

    /**
     * Inserts a records  with the given data.
     *
     * @param  mixed   $data       Typically an array of key/value pairs that specify the new data with which
     *                             the records will be updated. For SQL databases, this can optionally be
     *                             an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  array   $options    Any database-specific options to use when performing the operation.
     * @return boolean             Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public function insert($data, $options = [])
    {
        throw new ChaosException("Missing `insert()` implementation for `{$this->_model}`'s schema.");
    }

    /**
     * Updates multiple records with the given data, restricted by the given set of criteria (optional).
     *
     * @param  mixed $data       Typically an array of key/value pairs that specify the new data with which
     *                           the records will be updated. For SQL databases, this can optionally be
     *                           an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  mixed $conditions An array of key/value pairs representing the scope of the records
     *                           to be updated.
     * @param  array $options    Any database-specific options to use when performing the operation.
     * @return boolean           Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public function update($data, $conditions = [], $options = [])
    {
        throw new ChaosException("Missing `update()` implementation for `{$this->_model}`'s schema.");
    }

    /**
     * Removes multiple documents or records based on a given set of criteria. **WARNING**: If no
     * criteria are specified, or if the criteria (`$conditions`) is an empty value (i.e. an empty
     * array or `null`), all the data in the backend data source (i.e. table or collection) _will_
     * be deleted.
     *
     * @param mixed    $conditions An array of key/value pairs representing the scope of the records or
     *                             documents to be deleted.
     * @param array    $options    Any database-specific options to use when performing the operation. See
     *                             the `delete()` method of the corresponding backend database for available
     *                             options.
     * @return boolean             Returns `true` if the remove operation succeeded, otherwise `false`.
     */
    public function delete($options = [])
    {
        throw new ChaosException("Missing `delete()` implementation for `{$this->_model}`'s schema.");
    }

    /**
     * Returns the last insert id from the database.
     *
     * @return mixed Returns the last insert id.
     */
    public function lastInsertId()
    {
        throw new ChaosException("Missing `lastInsertId()` implementation for `{$this->_model}`'s schema.");
    }

}
