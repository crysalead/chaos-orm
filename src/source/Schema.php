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
     * The datasource adpater
     *
     * @var object
     */
    protected $_adapter = null;

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
            'adapter' => null,
            'locked' => true,
            'fields' => [],
            'handlers' => [],
            'meta' => []
        ];
        $config = Set::merge($defaults, $config);

        $this->_name = $config['name'];
        $this->_adapter = $config['adapter'];
        $this->_locked = $config['locked'];
        $this->_fields = $config['fields'];
        $this->_handlers = $config['handlers'];
        $this->_meta = $config['meta'];

        foreach ($config['fields'] as $key => $value) {
            $this->_fields[$key] = $this->_normalizeField($value);
        }
    }

    /**
     * Normalize a field
     *
     * @param  array $field A field array
     * @return array A normalized field array
     */
    protected function _normalizeField($field)
    {
        if (is_string($field)) {
            return ['type' => $field];
        }
        if (isset($field[0]) && !isset($field['type'])) {
            $field['type'] = $field[0];
            unset($field[0]);
            return $field;
        }
        return $field;
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
     * Appends additional fields to the schema. Will not overwrite existing fields if any conflicts
     * arise.
     *
     * @param array $fields New schema data.
     */
    public function append($fields, $meta = [])
    {
        if ($this->_locked) {
            throw new SourceException("Schema cannot be modified.");
        }
        $this->_fields += $fields;
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
     * Create the schema.
     *
     * @return boolean
     * @throws chaos\SourceException If no adapter is connected or the schema name is missing.
     */
    public function create()
    {
        $this->_adapterCheck();
        return $this->_adapter->createSchema($this->_name, $this);
    }

    /**
     * Drop the schema
     *
     * @return boolean
     * @throws chaos\SourceException If no adapter is connected or the schema name is missing.
     */
    public function drop($method, $params = [])
    {
        $this->_adapterCheck();
        return $this->_adapter->dropSchema($this->_name, $this);
    }

    /**
     * Check if the schema is correctly connected to a data source and has a valid name.
     *
     * @throws chaos\SourceException If no adapter is connected or the schema name is missing.
     */
    protected function _adapterCheck()
    {
        if (!$this->_adapter) {
            throw new SourceException("Missing data adapter for this schema.");
        }
        if (!isset($this->_name)) {
            throw new SourceException("Missing name for this schema.");
        }
    }

    public function cast($object, $key, $data, $options = [])
    {
        $defaults = [
            'parent' => null,
            'pathKey' => null,
            'model' => null,
            'wrap' => true,
            'first' => false
        ];
        $options += $defaults;

        $basePathKey = $options['pathKey'];
        $model = (!$options['model'] && $object) ? $object->model() : $options['model'];
        $classes = $this->_classes;

        $fieldName = is_int($key) ? null : $key;
        $pathKey = $basePathKey;

        if ($fieldName) {
            $pathKey = $basePathKey ? "{$basePathKey}.{$fieldName}" : $fieldName;
        }

        if ($data instanceof $classes['set'] || $data instanceof $classes['entity']) {
            return $data;
        }
        if (is_object($data) && !$this->is('array', $pathKey)) {
            return $data;
        }
        return $this->_castArray($object, $data, $pathKey, $options, $defaults);
    }

    protected function _castArray($object, $val, $pathKey, $options, $defaults)
    {
        $isArray = $this->is('array', $pathKey) && (!$object instanceof $this->_classes['set']);
        $isObject = ($this->type($pathKey) === 'object');
        $valIsArray = is_array($val);
        $numericArray = false;
        $class = 'entity';

        if (!$valIsArray && !$isArray) {
            return $this->_castType($val, $pathKey);
        }

        if ($valIsArray) {
            $numericArray = !$val || array_keys($val) === range(0, count($val) - 1);
        }

        if ($isArray || ($numericArray && !$isObject)) {
            $val = $valIsArray ? $val : array($val);
            $class = 'set';
        }

        if ($options['wrap']) {
            $config = array(
                'parent' => $options['parent'],
                'model' => $options['model'],
                'schema' => $this
            );
            $config += compact('pathKey') + array_diff_key($options, $defaults);

            if (!$pathKey && $model = $options['model']) {
                $exists = is_object($object) ? $object->exists() : false;
                $config += array('class' => $class, 'exists' => $exists, 'defaults' => false);
                $val = $model::create($val, $config);
            } else {
                $config['data'] = $val;
                $val = $this->_instance($class, $config);
            }
        } elseif ($class === 'set') {
            $val = $val ?: array();
            foreach ($val as &$value) {
                $value = $this->_castType($value, $pathKey);
            }
        }
        return $val;
    }

    /**
     * Casts a scalar (non-object/array) value to its corresponding database-native value or custom
     * value object based on a handler assigned to `$field`'s data type.
     *
     * @param  mixed  $value The value to be cast.
     * @param  string $field The name of the field that `$value` is or will be stored in. If it is a
     *                nested field, `$field` should be the full dot-separated path to the
     *                sub-object's field.
     * @return mixed  Returns the result of `$value`, modified by a matching handler data type
     *                handler, if available.
     */
    protected function _castType($value, $field)
    {
        if ($this->is('null', $field) && ($value === null || $value === "")) {
            return null;
        }
        if (!is_scalar($value)) {
            return $value;
        }
        $type = $this->type($field);
        return isset($this->_handlers[$type]) ? $this->_handlers[$type]($value) : $value;
    }

    /**
     * Reset the instance.
     */
    public function reset() {
        $this->_name = null;
        $this->_adapter = null;
        $this->_meta = [];
        $this->_fields = [];
        $this->_locked = true;
    }
}

?>