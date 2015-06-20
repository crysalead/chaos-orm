<?php
namespace chaos\model;

use Iterator;
use set\Set;
use chaos\SourceException;

class Schema
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'entity'       => 'chaos\model\Model',
        'set'          => 'collection\Collection',
        'relationship' => 'chaos\model\Relationship'
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
     * List of valid relation types.
     *
     * @var array
     */
    protected $_relationTypes = ['belongsTo', 'hasOne', 'hasMany', 'hasManyThrough'];

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
     *                      - `'connection'`  _object_ : The connection instance.
     *                      - `'source'`      _string_ : The source name.
     *                      - `'locked'`      _boolean_: set the ability to dynamically add/remove fields (defaults to `false`).
     *                      - `'classes'`     _array_  : The class dependencies.
     *                      - `'primaryKey'`  _array_  : The primary key.
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
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes'      => $this->_classes,
            'source'       => null,
            'connection'   => null,
            'conventions'  => null,
            'locked'       => true,
            'fields'       => [],
            'meta'         => [],
            'handlers'     => []
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
     * Set a field.
     *
     * @param  string $name The field name.
     * @return object       Returns `$this`.
     */
    public function set($name, $value = [])
    {
        $field = $this->_fields[$name] = $this->_initField($value);

        if ($field['type'] === 'object') {
            $relationship = $this->_classes['relationship'];

            $this->bind($name, [
                'relation' => $field['array'] ? 'hasMany' : 'hasOne',
                'to'       => isset($field['class']) ? $field['class'] : 'chaos\model\Model',
                'link'     => $relationship::LINK_EMBEDDED
            ]);
        }
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
            'array' => false,
            'null'  => true
        ];
        if (is_string($field)) {
            $field = ['type' => $field];
        } elseif (isset($field[0])) {
            $field['type'] = $field[0];
            unset($field[0]);
        }
        $type = $field['type'];
        if (isset($this->_handlers[$type])) {
            $field['format'] = $this->_handlers[$type];
        }

        return $field + $defaults;
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
     * Gets/sets the primary key of this schema
     *
     * @param  string $primaryKey The name or the primary key or `null` to get the defined one.
     * @return string
     */
    public function primaryKey($primaryKey = null)
    {
        if (func_num_args()) {
            $this->_primaryKey = $primaryKey;
        }
        return $this->_primaryKey;
    }

    /**
     * Gets all fields.
     *
     * @param  array $attribute  An attribute name to filter on. If `null` returns all attributes.
     * @return array
     */
    public function fields($attribute = null)
    {
        if (!func_num_args()) {
            return $this->_fields;
        }
        $result = [];
        foreach ($this->_fields as $key => $value) {
            if ($attribute !== 'default' || $value['type'] !== 'object') {
                $result[$key] = $this->field($key, $attribute);
            }
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
        $field = isset($this->_fields[$name]) ? $this->_fields[$name] : null;

        if ($field && $attribute) {
            return isset($field[$attribute]) ? $field[$attribute] : null;
        }
        return $field;
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
     * Returns all schema field names.
     *
     * @return array An array of field names.
     */
    public function names()
    {
        return array_keys($this->_fields);
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
        if ($this->_locked) {
            throw new SourceException("Schema cannot be modified.");
        }
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $this->_fields[$key] = $this->_initField($value);
            }
        } else {
            $this->_fields = $schema->fields() + $this->_fields;
        }
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
            return $this->_meta = $name;
        }
        if ($num === 2) {
            return $this->_meta[$name] = $value;
        }
        return isset($this->_meta[$name]) ? $this->_meta[$name] : [];
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
        $config = ['type' => 'object'] + $config;

        if (!isset($config['relation']) || !in_array($config['relation'], $this->_relationTypes)) {
            throw new SourceException("Unexisting binding relation `{$config['relation']}` for `'{$name}'`.");
        }
        if (!isset($config['to'])) {
            throw new SourceException("Binding requires `'to'` option to be set.");
        }
        if (!isset($config['from'])) {
            $config['from'] = $this->_classes['entity'];
        }
        $from = $config['from'];
        if (($pos = strrpos('\\', $config['to'])) !== false) {
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

        if (isset($this->_relations[$name])) {
            $config = $this->_relations[$name];
            $config['type'] = $config['relation'];
            unset($config['relation']);
            return $this->_relationships[$name] = $this->_relationship($name, $config);
        }
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
        if (in_array($type, $this->_relationTypes, true)) {
            foreach ($relations as $field => $relation) {
                if ($relation['type'] === $name) {
                    $result[] = $field;
                }
            }
        }
        return $result;
    }

    /**
     * Relationship instance factory.
     *
     * @param  string $name   The name of the relation.
     * @param  array  $config The relationship options.
     * @return object         Returns the created relationship.
     */
    protected function _relationship($name, $config = [])
    {
        $conventions = $this->_conventions;
        $config += compact('name', 'conventions');
        $relationship = $this->_classes['relationship'];
        return new $relationship($config);
    }

    /**
     * Cast data according to the schema definition.
     *
     * @param  array  $name    The field name.
     * @param  array  $data    Some data to cast.
     * @param  array  $options Options for the casting.
     * @return object          The casted data.
     */
    public function cast($name, $data, $options = [])
    {
        if (is_object($data)) {
            return $data;
        }

        $defaults = [
            'parent'    => null,
            'type'      => 'entity',
            'model'     => $this->_classes['entity'],
            'exists'    => false
        ];
        $options += $defaults;

        $name = $options['rootPath'] ? $options['rootPath'] . '.' . $name : $name;

        if ($name === null) {
            $model = $options['model'];
            return $model::create($data, $options);
        }

        $properties = $this->properties($name);
        $options['rootPath'] = $name;

        return $this->_cast($properties, $data, $options);
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
        $model = $options['model'];

        if ($properties['array']) {
            $options['type'] = 'set';
            return $model::create($data, $options);
        }
        if ($properties['type'] === 'object') {
            if (isset($properties['model'])) {
                $options['model'] = $properties['model'];
            }
            return $model::create($data, $options);
        }
        if ($properties['null'] && ($data === null || $data === '')) {
            return;
        }
        return isset($properties['format']) ? $properties['format']($data) : $data;
    }

    /**
     * Gets properties of a schema field/relation
     *
     * @param  string $name The field or relation name.
     * @return array  The properties array or `false` if no properties exists.
     */
    public function properties($name)
    {
        if (isset($this->_fields[$name])) {
            return $this->_fields[$name];
        } elseif (isset($this->_relations[$name])) {
            return $this->_relations[$name];
        }
        return $this->_initField('string');
    }

}
