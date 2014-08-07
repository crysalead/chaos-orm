<?php
namespace chaos\Source;

use set\Set;
use chaos\SourceException;

/**
 * This class encapsulates a schema definition, usually for a model class, and is comprised
 * of named fields and types.
 */
class Schema
{
    /**
     * The name of the schema
     *
     * @var string
     */
    protected $_name = null;

    /**
     * The connection to the datasource
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * Is the schema is locked
     *
     * @var boolean
     */
    protected $_locked = true;

    /**
     * The fields
     *
     * @var array
     */
    protected $_fields = [];

    /**
     * The meta
     *
     * @var array
     */
    protected $_meta = [];

    /**
     * Casting handlers
     *
     * @var array
     */
    protected $_handlers = [];

    /**
     * Constructor
     *
     * @param array $config Available options are:
     *              - `'locked'`: set the ability to dynamically add/remove fields (defaults to `false`).
     *              - `'fields'`: array of field definition where keys are field names and values are
     *                arrays with the following keys. All properties are optionnal except the `'type'`:
     *                  - `'type'`      : the type of the field.
     *                  - `'model'`     : the model to use to box the loaded data (default to '`null`').
     *                  - `'default'`   : the default value (default to '`null`').
     *                  - `'null'`      : allow null value (default to `'null'`).
     *                  - `'length'`    : the length of the data (default to `'null'`).
     *                  - `'precision'` : the precision (for decimals) (default to `'null'`).
     *                  - `'use'`       : the database type to override the associated type for
     *                                   this type (default to `'null'`).
     *                  - `'serial'`    : autoincremented field (default to `'null'`).
     *                  - `'primary'`   : primary key (default to `'null'`).
     *                  - `'unique'`    : unique key (default to `'null'`).
     *                  - `'foreignKey'`: foreign key (default to `'null'`).
     *              - `'handlers'`     : casting handlers.
     *              - `'meta'`: array of meta definitions for the schema. The definitions are related to
     *                the datasource. For the MySQL adapter the following options are available:
     *                  - `'charset'`   : the charset value to use for the table.
     *                  - `'collate'`   : the collate value to use for the table.
     *                  - `'engine'`    : the engine value to use for the table.
     *                  - `'tablespace'`: the tablespace value to use for the table.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name' => null,
            'connection' => null,
            'locked' => true,
            'fields' => [],
            'handlers' => [],
            'meta' => []
        ];
        $config = Set::merge($defaults, $config);

        $this->_name = $config['name'];
        $this->_connection = $config['connection'];
        $this->_locked = $config['locked'];
        $this->_fields = $config['fields'];
        $this->_handlers = $config['handlers'];
        $this->_meta = $config['meta'];

        foreach ($config['fields'] as $key => $value) {
            $this->_fields[$key] = $this->_initField($value);
        }
    }

    /**
     * Normalize a field
     *
     * @param  array $field A field array
     * @return array A normalized field array
     */
    protected function _initField($field)
    {
        if (is_string($field)) {
            $field = ['type' => $field];
        } elseif (isset($field[0]) && !isset($field['type'])) {
            $field['type'] = $field[0];
            unset($field[0]);
            return $field;
        }
        $type = $field['type'];
        if (isset($this->_handlers[$type])) {
            $field['format'] = $this->_handlers[$type];
        }
        return $field;
    }

    /**
     * Return the schema connection.
     *
     * @throws chaos\SourceException If no connection is defined.
     */
    public function connection()
    {
        if (!$this->_connection) {
            throw new SourceException("Missing data connection for this schema.");
        }
        return $this->_connection;
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
     * Returns a schema field attribute/attributes or all fields.
     *
     * @param  array $name A field name. If `null` returns all fields.
     * @param  array $key  An attribute name. If `null` returns all attributes.
     * @return mixed.
     */
    public function fields($name = null, $key = null)
    {
        if (!$name) {
            return $this->_fields;
        }
        $field = isset($this->_fields[$name]) ? $this->_fields[$name] : null;

        if ($field && $key) {
            return isset($field[$key]) ? $field[$key] : null;
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
        return $this->fields($name, 'type');
    }

    /**
     * Returns the default value of a field name.
     *
     * @param  string $name The field name.
     * @return array  The default value or `null` if not found.
     */
    public function defaults($name)
    {
        return $this->fields($name, 'default');
    }

    /**
     * Check if the schema has a field/some fields.
     *
     * @param  string|array $name The field name or an array of field names to check.
     * @return boolean Returns `true` if present, `false` otherwise.
     */
    public function has($name)
    {
        if (is_string($name)) {
            return isset($this->_fields[$name]);
        }
        if (is_array($name)) {
            return array_intersect($name, array_keys($this->_fields)) === $name;
        }
    }

    /**
     * Detects properties of a field, i.e. if it supports arrays.
     *
     * @param  string $condition
     * @param  string $name
     * @return boolean
     */
    public function is($condition, $name)
    {
        if (!isset($this->_fields[$name])) {
            return null;
        }
        return isset($this->_fields[$name][$condition]) && $this->_fields[$name][$condition];
    }

    /**
     * Returns the meta data associated to a field is some exists.
     *
     * @param  string $name The field name. If `null` returns all metas.
     * @return array  An array of meta datas or `null` if not found.
     */
    public function meta($name = null)
    {
        if ($name === null) {
            return $this->_meta;
        }
        return isset($this->_meta[$name]) ? $this->_meta[$name] : null;
    }

    /**
     * Appends additional fields to the schema. Will overwrite existing fields if a
     * conflicts arise.
     *
     * @param array $fields New schema data.
     * @param array $meta   New meta data.
     */
    public function append($fields, $meta = [])
    {
        if ($this->_locked) {
            throw new SourceException("Schema cannot be modified.");
        }
        foreach ($fields as $key => $value) {
            $this->_fields[$key] = $this->_initField($value);
        }
        $this->_meta += $meta;
    }

    /**
     * Merges another `Schema` object into the current one.
     *
     * @param object $schema Another `Schema` class object to be merged into the current one.
     *                       If this schema contains field names that conflict with existing
     *                       field names, the existing fields will be overwritten.
     */
    public function merge($schema)
    {
        if ($this->_locked) {
            throw new SourceException("Schema cannot be modified.");
        }
        $this->_fields = $schema->fields() + $this->_fields;
        $this->_meta = $schema->meta() + $this->_meta;
    }

    /**
     * Cast data according to the schema definition.
     *
     * @param  array  $key     The field name.
     * @param  array  $data    Some data to cast.
     * @param  array  $options Options for the casting.
     * @return object          The casted data.
     */
    public function cast($key, $data, $options = [])
    {
        $defaults = ['pathKey' => null, 'asContent' => false, 'exists' => false];
        $options += $defaults;

        $pathKey = $options['pathKey'];

        if (!$pathKey && !$key) {
            return $this->_autobox($data, $options);
        }

        is_int($key) ? $options['asContent'] = true : $pathKey = $pathKey ? "{pathKey}.{$key}" : $key;

        if (!isset($this->_fields[$pathKey])) {
            return $data;
        }

        $field = $this->_fields[$pathKey];

        if (!$field['array'] || $options['asContent']) {
            return $this->_cast($field, $data, $options['asContent']);
        }

        $data = is_array($data) ? $data :[$data];
        $options['class'] = 'set';
        $options['pathKey'] = $pathKey;
        return $this->_autobox($data, $options);
    }

    /**
     * Autobox some data into an object.
     *
     * @param  array  $data    Some data to autobox.
     * @param  array  $options Options for the autoboxing.
     * @return object          The autoboxed object.
     */
    protected function _autobox($data, $options = [])
    {
        if ($data instanceof $this->_classes['set'] || $data instanceof $this->_classes['entity']) {
            return $data;
        }
        $defaults = [
            'parent' => null,
            'pathKey' => null,
            'model' => null,
            'class' => 'entity',
            'schema' => $this
        ];
        $options += $defaults;
        if (!$model = $options['model']) {
            return $this->_instance($options['class'], $options + ['data' => $data]);
        }
        return $model::create($data, $options + ['defaults' => false]);
    }

    /**
     * Casting helper
     *
     * @param  array  $field     The field properties which define the casting.
     * @param  array  $data      Some data to cast.
     * @param  array  $asContent Cast as if it was a element of the field
     *                           (unused if the field properties doesn't correspond to an array).
     * @return mixed             The casted data.
     */
    protected function _cast($field, $data, $asContent = false)
    {
        if ($asContent && is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[] = $this->_cast($field, $value);
            }
            return $result;
        }
        if ($field['null'] && ($data === null || $data === '')) {
            return null;
        }
        return isset($field['format']) ? $field['format']($data) : $data;
    }

    /**
     * Reset the instance.
     */
    public function reset() {
        $this->_name = null;
        $this->_connection = null;
        $this->_meta = [];
        $this->_fields = [];
        $this->_locked = true;
    }
}

?>