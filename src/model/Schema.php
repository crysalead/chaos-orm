<?php
namespace chaos\model;

use Iterator;
use set\Set;
use chaos\SourceException;
use chaos\model\Model;

class Schema
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'collector'      => 'chaos\model\Collector',
        'relationship'   => 'chaos\model\Relationship',
        'belongsTo'      => 'chaos\model\relationship\BelongsTo',
        'hasOne'         => 'chaos\model\relationship\HasOne',
        'hasMany'        => 'chaos\model\relationship\HasMany',
        'hasManyThrough' => 'chaos\model\relationship\HasManyThrough'
    ];

    /**
     * The connection to the datasource.
     *
     * @var object
     */
    protected $_connection = null;

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
     * Is the schema is locked.
     *
     * @var boolean
     */
    protected $_locked = true;

    /**
     * The primary key.
     *
     * @var string
     */
    protected $_primaryKey = null;

    /**
     * The meta.
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
     *                      - `'primaryKey'`  _array_  : The primary key value (defaults to `id`).
     *                      - `'fields'`      _array_  : array of field definition where keys are field names and values are arrays
     *                                                   with the following keys. All properties are optionnal except the `'type'`:
     *                                                   - `'type'`       _string_ : the type of the field.
     *                                                   - `'default'`    _mixed_  : the default value (default to '`null`').
     *                                                   - `'null'`       _boolean_: allow null value (default to `'null'`).
     *                                                   - `'length'`     _integer_: the length of the data (default to `'null'`).
     *                                                   - `'precision'`  _integer_: the precision (for decimals) (default to `'null'`).
     *                                                   - `'use'`        _string_ : the database type to override the associated type for
     *                                                                               this type (default to `'null'`).
     *                                                   - `'serial'`     _string_ : autoincremented field (default to `'null'`).
     *                                                   - `'primary'`    _boolead_: primary key (default to `'null'`).
     *                                                   - `'unique'`     _boolead_: unique key (default to `'null'`).
     *                                                   - `'foreignKey'` _string_ : foreign key (default to `'null'`).
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
            'model'        => null,
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
        $this->_handlers = $config['handlers'];
        $this->_conventions = $config['conventions'] ?: new Conventions();

        $config += [
            'primaryKey' => $this->_conventions->apply('primaryKey')
        ];

        $this->_fields = $config['fields'];
        $this->_source = $config['source'];
        $this->_model = $config['model'];
        $this->_primaryKey = $config['primaryKey'];

        foreach ($config['fields'] as $key => $value) {
            $this->_fields[$key] = $this->_initField($value);
        }
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    Returns a connection instance.
     * @throws Exception Throws a `chaos\SourceException` if a connection isn't set.
     */
    public function connection($connection = null)
    {
        if (func_num_args()) {
            $this->_connection = $connection;
            return $this;
        }
        if (!$this->_connection) {
            throw new SourceException("Error, missing connection for this schema.");
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
     * @param  mixed $locked The locked value to set to none to get the current lock value.
     * @return mixed         A boolean value or `$this`.
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
     * @return array        If `$name` is a string, it returns the correcponding value
     *                      otherwise it returns meta datas array or `null` if not found.
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
     * Gets/sets the primary key of this schema
     *
     * @param  string $primaryKey The name or the primary key or `null` to get the defined one.
     * @return string
     */
    public function primaryKey($primaryKey = null)
    {
        if (!func_num_args()) {
            return $this->_primaryKey;
        }
        $this->_primaryKey = $primaryKey;
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
    public function fields($attribute = null)
    {
        if (!$attribute) {
            $fields = [];
            foreach ($this->_fields as $name => $value) {
                $fields[$name] = $this->field($name);
            }
            return $fields;
        }
        $result = [];
        foreach ($this->_fields as $name => $field) {
            $value = isset($field[$attribute]) ? $field[$attribute] : null;
            $result[$name] = $value;
        }
        return $result;
    }

    /**
     * Returns a schema field attribute.
     *
     * @param  array $name       A field name.
     * @param  array $attribute  An attribute name. If `null` returns all attributes.
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
        } else {
            $type = $field['type'];
            if (isset($this->_handlers[$type])) {
                $field['format'] = $this->_handlers[$type];
            }
        }
        return $field;
    }

    /**
     * Returns default values.
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
     * @return array  The type value or `null` if not found.
     */
    public function type($name)
    {
        return $this->field($name, 'type');
    }

    /**
     * Set a field.
     *
     * @param  string $name The field name.
     * @return object       Returns `$this`.
     */
    public function set($name, $params = [])
    {
        $field = $this->_initField($params);

        if ($field['type'] === 'object') {
            $relationship = $this->_classes['relationship'];

            $field += [
                'relation' => $field['array'] ? 'hasMany' : 'hasOne',
                'to'       => isset($field['to']) ? $field['to'] : Model::class,
                'link'     => $relationship::LINK_EMBEDDED
            ];

            $this->bind($name, $field);
        }
        $this->_fields[$name] = $field;

        return $this;
    }

    /**
     * Normalize a field.
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
     * @return boolean Returns `true` if present, `false` otherwise.
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
            $this->_fields = $fields->fields() + $this->_fields;
        }
        return $this;
    }

    /**
     * Lazy bind a relation
     *
     * @param  string    $name   The name of the relation (i.e. field name where it will be binded).
     * @param  array     $config The configuration that should be specified in the relationship.
     *                           See the `chaos\model\Relationship` class for more information.
     * @return boolean
     * @throws Exception         Throws a `chaos\SourceException` if the config has no type option defined.
     */
    public function bind($name, $config = [])
    {
        $config += [
            'from' => $this->model(),
            'to'   => null
        ];
        $config['type'] = 'object';

        if (!isset($config['relation']) || !isset($this->_classes[$config['relation']])) {
            throw new SourceException("Unexisting binding relation `{$config['relation']}` for `'{$name}'`.");
        }
        if (!$config['from']) {
            throw new SourceException("Binding requires `'from'` option to be set.");
        }
        if (!$config['to']) {
            if ($config['relation'] !== 'hasManyThrough') {
                throw new SourceException("Binding requires `'to'` option to be set.");
            }
        } elseif (($pos = strrpos('\\', $config['to'])) !== false) {
            $from = $config['from'];
            $config['to'] = substr($from, 0, $pos + 1) . $config['to'];
        }

        $this->_relations[$name] = $config;
        $this->_relationships[$name] = null;
        return true;
    }

    /**
     * Unbind a relation
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
            throw new SourceException("Relationship `{$name}` not found.");
        }
        $config = $this->_relations[$name];
        $relationship = $config['relation'];
        unset($config['relation']);

        $relation = $this->_classes[$relationship];
        return $this->_relationships[$name] = new $relation($config + [
            'schema'      => $this,
            'name'        => $name,
            'conventions' => $this->_conventions
        ]);
    }

    /**
     * Returns an array of relation names.
     *
     * @param  string $type A relation type name.
     * @return array        Returns an array of relation names.
     */
    public function relations($type = null)
    {
        $result = [];

        if ($type === null) {
            return array_keys($this->_relationships);
        }
        foreach ($relations as $field => $relation) {
            if ($relation['type'] === $name) {
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
        if (!isset($options['collector'])) {
            $collector = $this->_classes['collector'];
            $options['collector'] = new $collector();
        }

        $expanded = [];
        $relations = $this->_expandHasManyThrough(Set::normalize($relations), $expanded);

        $tree = Set::expand(array_fill_keys(array_keys($relations), []));

        foreach ($tree as $name => $subtree) {
            $rel = $this->relation($name);
            if ($rel->type() === 'hasManyThrough') {
                continue;
            }

            $to = $rel->to();
            $related = $rel->embed($collection, $options);

            $subrelations = [];
            foreach ($relations as $path => $value) {
                if (preg_match('~^'.$name.'\.(.*)$~', $path, $matches)) {
                    $subrelations[] = $matches[1];
                }
            }
            if ($subrelations) {
                $to::schema()->embed($related, $subrelations, $options);
            }
        }

        foreach ($expanded as $name) {
            $rel = $this->relation($name);
            $related = $rel->embed($collection, $options);
        }
        return $options['collector'];
    }

    /**
     * Helper which expands all `'hasManyThrough'` relations into their full path.
     *
     * @param  array $relations       The relations to eager load.
     * @param  array $expanded        The name of relations which was expanded.
     * @return array                  The relations to eager load with no more HasManyThrough relations.
     */
    protected function _expandHasManyThrough($relations, &$expanded)
    {
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
            $expanded[] = $name;
            unset($relations[$path]);
        }
        return $relations;
    }

    /**
     * Cast data according to the schema definition.
     *
     * @param  string $name    The field name.
     * @param  array  $data    Some data to cast.
     * @param  array  $options Options for the casting.
     * @return object          The casted data.
     */
    public function cast($name, $data, $options = [])
    {
        $defaults = [
            'parent'    => null,
            'type'      => 'entity',
            'model'     => Model::class,
            'rootPath'  => null,
            'exists'    => false
        ];
        $options += $defaults;

        $name = is_int($name) ? null : $name;

        if ($name) {
            $name = $options['rootPath'] ? $options['rootPath'] . '.' . $name : $name;
        } else {
            $name = $options['rootPath'];
        }

        if ($name === null) {
            $model = $options['model'];
            if (!is_object($data)) {
                return $model::create($data, $options);
            }
            if ($data instanceof $model) {
                return $data;
            }
            throw new SourceException("Invalid data, the passed object must be an instance of `{$model}`");
        }

        $properties = $this->_properties($name);

        if (is_object($data)) {
            if ($properties['type'] !== 'object') {
                return $data;
            }
            if (!$properties['array']) {
                $to = $properties['to'];
                if ($data instanceof $to) {
                    return $data;
                } else {
                    throw new SourceException("Invalid data, the passed object must be an instance of `{$to}`");
                }
            }
            $type = $properties['relation'] === 'hasManyThrough' ? 'through' : 'set';
            $collection = $this->_classes[$type];
            if ($data instanceof $collection) {
                return $data;
            } else {
                throw new SourceException("Invalid data, the passed object must be an instance of `{$collection}`");
            }
        }

        if (isset($this->_fields[$name])) {
            $options['rootPath'] = $name;
        }
        return $this->_cast($properties, $data, $options);
    }

    /**
     * Returns all field name attached properties.
     *
     * @param  string $name The field name.
     * @return array        The field name properties.
     */
    public function _properties($name)
    {
        if (isset($this->_fields[$name])) {
            return $this->_fields[$name];
        }
        if (isset($this->_relations[$name])) {
            $properties = $this->_relations[$name];
            if (!isset($properties['array'])) {
                $properties['array'] = !!preg_match('~Many~', $properties['relation']);
            }
            return $properties;
        }
        return $properties = $this->_initField('string');
    }

    /**
     * Casting helper
     *
     * @param  array  $properties The field properties which define the casting.
     * @param  array  $data       Some data to cast.
     * @param  array  $options    Options for the casting.
     * @return mixed              The casted data.
     */
    public function _cast($properties, $data, $options)
    {
        if (isset($properties['to'])) {
            $options['model'] = $properties['to'];
        }
        $model = $options['model'];

        if ($properties['array']) {
            if ($properties['relation'] === 'hasManyThrough') {
                $options['through'] = $properties['through'];
                $options['using'] = $properties['using'];
                $options['type'] = 'through';
            } else {
                $options['type'] = 'set';
            }
            return $model::create($data, $options);
        }
        if ($properties['type'] === 'object') {
            return $model::create($data, $options);
        }
        if ($properties['null'] && ($data === null || $data === '')) {
            return;
        }
        return isset($properties['format']) ? $properties['format']($data) : $data;
    }

    /**
     * The `'with'` option formatter function
     *
     * @return array The formatter with array
     */
    public function with($with)
    {
        if (!$with) {
            return  false;
        }
        if ($with === true) {
            $with = array_fill_keys($this->relations(), true);
        } else {
            $with = Set::expand(Set::normalize((array) $with));
        }
        return $with;
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    Returns a connection instance.
     * @throws Exception Throws a `chaos\SourceException` if a connection isn't set.
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
        throw new SourceException("Missing `query()` implementation for this schema.");
    }
}
