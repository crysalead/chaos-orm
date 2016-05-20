<?php
namespace Chaos;

use Traversable;
use Lead\Set\Set;
use Chaos\Collection\Collection;

class Document implements DataStoreInterface, \ArrayAccess, \Iterator, \Countable
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected static $_classes = [
        'collector'   => 'Chaos\Collector',
        'set'         => 'Chaos\Collection\Collection',
        'through'     => 'Chaos\Collection\Through',
        'conventions' => 'Chaos\Conventions',
        'validator'   => 'Lead\Validator\Validator'
    ];

    /**
     * Stores validator instances.
     *
     * @var array
     */
    protected static $_validators = [];

    /**
     * MUST BE re-defined in sub-classes which require some different conventions.
     *
     * @var object A naming conventions.
     */
    protected static $_conventions = null;

    /**
     * MUST BE re-defined in sub-classes which require a different schema.
     *
     * @var string
     */
    protected static $_definition = 'Chaos\Schema';

    /**
     * The collector instance.
     *
     * @var object
     */
    protected $_collector = null;

    /**
     * If this record is chained off of another, contains the origin object.
     *
     * @var object
     */
    protected $_parent = null;

    /**
     * Stores the document schema.
     *
     * @var object
     */
    protected $_schema = null;

    /**
     * If this instance has a parent, this value indicates the parent field path.
     *
     * @var string
     */
    protected $_rootPath = '';

    /**
     * Cached value indicating whether or not this instance exists somehow. If this instance has been loaded
     * from the database, or has been created and subsequently saved this value should be automatically
     * setted to `true`.
     *
     * @var boolean
     */
    protected $_exists = false;

    /**
     * Loaded data on construct.
     *
     * @var array
     */
    protected $_persisted = [];

    /**
     * Contains the values of updated fields. These values will be persisted to the backend data
     * store when the document is saved.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * The list of validation errors associated with this object, where keys are field names, and
     * values are arrays containing one or more validation error messages.
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Workaround to allow consistent `unset()` in `foreach`.
     *
     * Note: the edge effet of this behavior is the following:
     * {{{
     *   $entity = new Model(['data' => [ 'a' => '1', 'b' => '2', 'c' => '3']]);
     *   unset($entity['a']);
     *   $entity->next();   // returns 2 instead of 3 when no `skipNext`
     * }}}
     */
    protected $_skipNext = false;

    /**
     * Gets/sets the conventions object to which this model is bound.
     *
     * @param  object $conventions The conventions instance to set or none to get it.
     * @return mixed               The conventions instance on get.
     */
    public static function conventions($conventions = null)
    {
        if (func_num_args()) {
            static::$_conventions = $conventions;
            return;
        }
        if (!static::$_conventions) {
            $conventions = static::$_classes['conventions'];
            static::$_conventions = new $conventions();
        }
        return static::$_conventions;
    }

    /**
     * Gets the Document schema definition.
     *
     * @return object The schema instance.
     */
    public static function definition()
    {
        $definition = static::$_definition;
        $schema = new $definition([
            'classes'     => ['entity' => Document::class] + static::$_classes,
            'conventions' => static::conventions(),
            'model'       => Document::class
        ]);
        $schema->locked(false);
        return $schema;
    }

    /**
     * Instantiates a new record or document object, initialized with any data passed in. For example:
     *
     * ```php
     * $post = Posts::create(['title' => 'New post']);
     * echo $post->title; // echoes 'New post'
     * $success = $post->save();
     * ```
     *
     * Note that while this method creates a new object, there is no effect on the database until
     * the `save()` method is called.
     *
     * In addition, this method can be used to simulate loading a pre-existing object from the
     * database, without actually querying the database:
     *
     * ```php
     * $post = Posts::create(['id' => $id, 'moreData' => 'foo'], ['exists' => true]);
     * $post->title = 'New title';
     * $success = $post->save();
     * ```
     *
     * This will create an update query against the object with an ID matching `$id`. Also note that
     * only the `title` field will be updated.
     *
     * @param  array  $data    Any data that this object should be populated with initially.
     * @param  array  $options Options to be passed to item.
     *                         - `'type'`       _string_ : can be `'entity'` or `'set'`. `'set'` is used if the passed data represent a collection
     *                           of entities. Default to `'entity'`.
     *                         - `'exists'`     _mixed_  : corresponds whether the entity is present in the datastore or not.
     *                         - `'autoreload'` _boolean_: sets the specific behavior when exists is `null`. A '`true`' value will perform a
     *                           reload of the entity from the datasource. Default to `'true'`.
     *                         - `'defaults'`   _boolean_: indicates whether the entity needs to be populated with their defaults values on creation.
     *                         - `'model'`      _string_ : the model to use for instantiating the entity. Can be useful for implementing
     *                                                     som Single Table Inheritance.
     * @return object          Returns a new, un-saved record or document object. In addition to
     *                         the values passed to `$data`, the object will also contain any values
     *                         assigned to the `'default'` key of each field defined in the schema.
     */
    public static function create($data = [], $options = [])
    {
        $defaults = [
            'type'   => 'entity',
            'exists' => false,
            'schema' => null,
            'model'  => static::class
        ];
        $options += $defaults;
        $options['defaults'] = !$options['exists'];

        $type = $options['type'];

        if ($type === 'entity') {
            $classname = $options['model'];
            $options['schema'] = $options['model'] === Document::class ? $options['schema'] : null;
        } else {
            $classname = static::$_classes[$options['type']];
            $model = $options['model'];
            $options['schema'] = $model::definition();
        }
        $options = ['data' => $data] + $options;

        return new $classname($options);
    }

    /**
     * Gets/sets the validator instance.
     *
     * @param  object $validator The validator instance to set or none to get it.
     * @return mixed             The validator instance on get.
     */
    public static function validator($validator = null)
    {
        if (func_num_args()) {
            static::$_validators[static::class] = $validator;
            return;
        }
        $self = static::class;
        if (isset(static::$_validators[$self])) {
            return static::$_validators[$self];
        }
        $class = static::$_classes['validator'];
        $validator = static::$_validators[$self] = new $class();
        static::_rules($validator);
        return $validator;
    }

    /***************************
     *
     *  Document related methods
     *
     ***************************/

    /**
     * Creates a new record object with default values.
     *
     * @param array $config Possible options are:
     *                      - `'collector'`  _object_ : A collector instance.
     *                      - `'parent'`     _object_ : The parent instance.
     *                      - `'rootPath'`   _string_ : A dotted field names path (for embedded entities).
     *                      - `'exists'`     _boolean_: A boolean or `null` indicating if the entity exists.
     *                      - `'autoreload'` _boolean_: If `true` and exists is `null`, autoreload the entity
     *                                                  from the datasource
     *                      - `'data'`       _array_  : The entity's data.
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'collector'  => null,
            'parent'     => null,
            'schema'     => null,
            'rootPath'   => null,
            'exists'     => false,
            'defaults'   => false,
            'data'       => [],
            'autoreload' => true
        ];
        $config += $defaults;
        $this->collector($config['collector']);
        $this->parent($config['parent']);
        $this->exists($config['exists']);
        $this->rootPath($config['rootPath']);
        $this->schema($config['schema']);

        if ($config['defaults'] && !$config['rootPath']) {
            $config['data'] = Set::merge($this->schema()->defaults(), $config['data']);
        }

        $this->set($config['data']);
        $this->_persisted = $this->_data;
    }

    /**
     * Gets the model name.
     *
     * @return string Returns the entity's model name.
     */
    public function model()
    {
        return static::class;
    }

    /**
     * Gets/sets the schema instance.
     *
     * @param  object schema The schema instance to set or none to get it.
     * @return mixed         The schema instance or `$this` on set.
     */
    public function schema($schema = null)
    {
        if (func_num_args()) {
            $this->_schema = $schema;
            return $this;
        }
        if (!$this->_schema) {
            $this->_schema = static::definition();
        }
        return $this->_schema;
    }

    /**
     * Gets/sets the collector instance.
     *
     * @param  object $collector The collector instance to set or none to get it.
     * @return object            The collector instance on set or `$this` otherwise.
     */
    public function collector($collector = null)
    {
        if (func_num_args()) {
            $this->_collector = $collector;
            return $this;
        }
        if (!$this->_collector) {
            $collector = static::$_classes['collector'];
            $this->_collector = new $collector();
        }
        return $this->_collector;
    }

    /**
     * Gets/sets the parent.
     *
     * @param  object $parent The parent instance to set or `null` to get it.
     * @return mixed          Returns the parent value on get or `$this` otherwise.
     */
    public function parent($parent = null)
    {
        if (!func_num_args()) {
            return $this->_parent;
        }
        $this->_parent = $parent;
        return $this;
    }

    /**
     * Gets/sets whether or not this instance has been persisted somehow.
     *
     * @param  boolean $exists The exists value to set or `null` to get the current one.
     * @return mixed           Returns the exists value on get or `$this` otherwise.
     */
    public function exists($exists = null)
    {
        if (!func_num_args()) {
            return $this->_exists;
        }
        $this->_exists = $exists;
        return $this;
    }

    /**
     * Gets/sets the rootPath (embedded entities).
     *
     * @param  string $rootPath The rootPath value to set or `null` to get the current one.
     * @return mixed            Returns the rootPath value on get or `$this` otherwise.
     */
    public function rootPath($rootPath = null)
    {
        if (!func_num_args()) {
            return $this->_rootPath;
        }
        $this->_rootPath = $rootPath;
        return $this;
    }


    /**
     * Returns the current data.
     *
     * @param  string $name If name is defined, it'll only return the field value.
     * @return array.
     */
    public function get($name = null)
    {
        if (!func_num_args()) {
            return $this->_data;
        }
        $keys = is_array($name) ? $name : explode('.', $name);
        $name = array_shift($keys);
        if (!$name) {
            throw new ChaosException("Field name can't be empty.");
        }

        if ($keys) {
            $value = $this->get($name);
            if (!$value instanceof DataStoreInterface) {
                throw new ChaosException("The field: `" . $name . "` is not a valid document or entity.");
            }
            return $value->get($keys);
        }

        $schema = $this->schema();
        $fieldname = $this->rootPath() ? $this->rootPath() . '.' . $name : $name;

        if (!$schema->has($fieldname)) {
            if (array_key_exists($name, $this->_data)) {
                return $this->_data[$name];
            } elseif ($schema->hasRelation($fieldname)) {
                return $this->_data[$name] = $schema->relation($fieldname)->get($this);
            }
            return;
        }

        $field = $schema->field($fieldname);

        if (!empty($field['getter'])) {
            $value = $field['getter']($this, array_key_exists($name, $this->_data) ? $this->_data[$name] : null, $name);
        } elseif (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        } elseif ($schema->hasRelation($fieldname)) {
            return $this->_data[$name] = $schema->relation($fieldname)->get($this);
        } elseif ($field['type'] === 'object') {
            $value = [];
        } else {
            return;
        }

        $value = $schema->cast($name, $value, [
            'collector' => $this->collector(),
            'parent'    => $this,
            'rootPath'  => $this->rootPath(),
            'defaults'  => true,
            'exists'    => $this->exists()
        ]);
        if (!empty($field['virtual'])) {
            return $value;
        }
        return $this->_data[$name] = $value;
    }

    /**
     * Sets one or several properties.
     *
     * @param  mixed $name    A field name or an associative array of fields and values.
     * @param  array $data    An associative array of fields and values or an options array.
     * @param  array $options An options array.
     * @return object         Returns `$this`.
     */
    public function set($name, $data = [])
    {
        if (func_num_args() >= 2) {
            $this->_set($name, $data);
            return $this;
        }
        $options = $data;
        $data = $name;
        if (!is_array($data)) {
            throw new ChaosException('An array is required to set data in bulk.');
        }
        foreach ($data as $name => $value) {
            $this->_set($name, $value);
        }
        return $this;
    }

    /**
     * Helper for the `set()` method.
     *
     * Ps: it allow to use scalar datas for relations. Indeed, on form submission relations datas are
     * provided by a select input which generally provide such kind of array:
     *
     * ```php
     * $array = [
     *     'id' => 3
     *     'comments' => [
     *         '5', '6', '9
     *     ]
     * ];
     * ```
     *
     * To avoid painfull pre-processing, this function will automagically manage such relation
     * array by reformating it into the following on autoboxing:
     *
     * ```php
     * $array = [
     *     'id' => 3
     *     'comments' => [
     *         ['id' => '5'],
     *         ['id' => '6'],
     *         ['id' => '9']
     *     ],
     * ];
     * ```
     *
     * @param string $offset  The field name.
     * @param mixed  $data    The value to set.
     * @param array  $options An options array.
     */
    protected function _set($name, $data)
    {
        $keys = is_array($name) ? $name : explode('.', $name);

        $name = array_shift($keys);
        if (!$name) {
            throw new ChaosException("Field name can't be empty.");
        }

        if ($keys) {
            $value = $this->get($name);

            if (!array_key_exists($name, $this->_data)) {
                $this->_set($name, static::create());
            }
            if (!$this->_data[$name] instanceof DataStoreInterface) {
                throw new ChaosException("The field: `" . $name . "` is not a valid document or entity.");
            }
            $this->_data[$name]->set($keys, $data);
            return;
        }

        $schema = $this->schema();

        $value = $this->schema()->cast($name, $data, [
            'collector' => $this->collector(),
            'parent'    => $this,
            'rootPath'  => $this->rootPath(),
            'defaults'  => true,
            'exists'    => $this->exists()
        ]);

        $fieldname = $this->rootPath() ? $this->rootPath() . '.' . $name : $name;
        if ($schema->isVirtual($fieldname)) {
            return;
        }

        $this->_data[$name] = $value;
    }

    /**
     * Allows fields to be accessed as an array, i.e. `$entity['id']`.
     *
     * @param  string $offset The field name.
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        $result = $this->get($offset);
        return $result;
    }

    /**
     * Allows to assign as an array, i.e. `$entity['id'] = $id`.
     *
     * @param  string $offset The field name.
     * @param  mixed  $value  The value.
     */
    public function offsetSet($offset, $value)
    {
        return $this->_set($offset, $value);
    }

    /**
     * Allows test existance as an array, i.e. `isset($entity['id'])`.
     *
     * @param  string $offset The field name.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        $keys = is_array($offset) ? $offset : explode('.', $offset);
        if (!$keys) {
            return false;
        }

        $offset = array_shift($keys);
        if ($keys) {
            $value = $this->get($offset);
            if ($value instanceof Document) {
                return $value->offsetExists($keys);
            }
            return false;
        }
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Allows to unset as an array, i.e. `unset($entity['id'])`.
     *
     * @param  string $offset The field name.
     */
    public function offsetUnset($offset)
    {
        $keys = is_array($offset) ? $offset : explode('.', $offset);
        if (!$keys) {
            return;
        }

        $offset = array_shift($keys);
        if ($keys) {
            $value = $this->get($offset);
            if ($value instanceof Document) {
                $value->offsetUnset($keys);
            }
            return;
        }
        unset($this->{$offset});
    }

    /**
     * Returns a string representation of the instance.
     *
     * @return string
     */
    public function title()
    {
        return $this->title ?: $this->name;
    }

    /**
     * Returns the key of the current item.
     *
     * @return scalar Scalar on success or `null` on failure.
     */
    public function key()
    {
        return key($this->_data);
    }

    /**
     * Returns the current item.
     *
     * @return mixed The current item or `false` on failure.
     */
    public function current()
    {
        return current($this->_data);
    }

    /**
     * Moves backward to the previous item.  If already at the first item,
     * moves to the last one.
     *
     * @return mixed The current item after moving or the last item on failure.
     */
    public function prev()
    {
        $value = prev($this->_data);
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Move forwards to the next item.
     *
     * @return The current item after moving or `false` on failure.
     */
    public function next()
    {
        $value = $this->_skipNext ? current($this->_data) : next($this->_data);
        $this->_skipNext = false;
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Rewinds to the first item.
     *
     * @return mixed The current item after rewinding.
     */
    public function rewind()
    {
        return reset($this->_data);
    }

    /**
     * Moves forward to the last item.
     *
     * @return mixed The current item after moving.
     */
    public function end()
    {
        end($this->_data);
        return current($this->_data);
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        return key($this->_data) !== null;
    }

    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    public function count() {
        return count($this->_data);
    }

    /**
     * Overloading for reading inaccessible properties.
     *
     * @param  string $name Property name.
     * @return mixed        Result.
     */
    public function &__get($name)
    {
        $value = $this->get($name);
        return $value;
    }

    /**
     * Overloading for writing to inaccessible properties.
     *
     * @param  string $name  Property name.
     * @param  string $value Property value.
     * @return mixed         Result.
     */
    public function __set($name, $value)
    {
        $this->_set($name, $value);
    }

    /**
     * Overloading for calling `isset()` or `empty()` on inaccessible properties.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->_data);
    }

    /**
     * Unsets a property.
     *
     * @param string $name The name of the field to remove.
     */
    public function __unset($name)
    {
        $this->_skipNext = $name === key($this->_data);
        unset($this->_data[$name]);
    }

    /**
     * Exports the entity into an array based representation.
     *
     * @param  array $options Some exporting options. Possibles values are:
     *                        - `'embed'` _array_: Indicates the relations to embed for the export.
     * @return mixed          The exported result.
     */
    public function data($options = [])
    {
        return $this->to('array', $options);
    }

    /**
     * Returns the persisted data (i.e the data in the datastore) of the entity.
     *
     * @param  string $field A field name or `null` to get all persisted data.
     * @return mixed
     */
    public function persisted($field = null)
    {
        if (!$field) {
            return $this->_persisted;
        }
        return isset($this->_persisted[$field]) ? $this->_persisted[$field] : null;
    }

    /**
     * Gets the modified state of a given field or, if no field is given, gets the state of the whole entity.
     *
     * @param  string $field The field name to check its state.
     * @return array         Returns `true` if a field is given and was updated, `false` otherwise.
     *                       If no field is given returns an array of all modified fields and their
     *                       original values.
     */
    public function modified($field = null)
    {
        if (!$this->exists()) {
            return true;
        }
        $schema = $this->schema();

        $result = [];
        $fields = $field ? [$field] : array_keys($this->_data + $this->_persisted);

        foreach ($fields as $key) {
            if (!array_key_exists($key, $this->_data)) {
                if (array_key_exists($key, $this->_persisted)) {
                    $result[$key] = $this->_persisted[$key];
                }
                continue;
            }
            if (!array_key_exists($key, $this->_persisted)) {
                if (!$schema->hasRelation($key)) {
                    $result[$key] = null;
                }
                continue;
            }
            $modified = false;
            $value = array_key_exists($key, $this->_data) ? $this->_data[$key] : $this->_persisted[$key];
            if (method_exists($value, 'modified')) {
                $modified = $this->_persisted[$key] !== $value || $value->modified();
            } elseif (is_object($value)) {
                $modified = $this->_persisted[$key] != $value;
            } else {
                $modified = $this->_persisted[$key] !== $value;
            }
            if ($modified) {
                $result[$key] = $this->_persisted[$key];
            }
        }
        if ($field) {
            return !empty($result);
        }
        $result = array_keys($result);
        $result = $field ? $result : !!$result;
        return $result;
    }

    /**
     * Validates the entity data.
     *
     * @param  array  $options Available options:
     *                         - `'events'` _mixed_    : A string or array defining one or more validation
     *                           events. Events are different contexts in which data events can occur, and
     *                           correspond to the optional `'on'` key in validation rules. For example, by
     *                           default, `'events'` is set to either `'create'` or `'update'`, depending on
     *                           whether the entity already exists. Then, individual rules can specify
     *                           `'on' => 'create'` or `'on' => 'update'` to only be applied at certain times.
     *                           You can also set up custom events in your rules as well, such as `'on' => 'login'`.
     *                           Note that when defining validation rules, the `'on'` key can also be an array of
     *                           multiple events.
     *                         - `'required'` _boolean_ : Sets the validation rules `'required'` default value.
     *                         - `'embed'`    _array_   : List of relations to validate.
     * @return boolean         Returns `true` if all validation rules on all fields succeed, otherwise
     *                         `false`. After validation, the messages for any validation failures are assigned
     *                         to the entity, and accessible through the `errors()` method of the entity object.
     */
    public function validate($options = [])
    {
        $defaults = [
            'events'   => $this->exists() !== false ? 'update' : 'create',
            'required' => $this->exists() !== false ? false : true,
            'embed'     => true
        ];
        $options += $defaults;
        $validator = static::validator();

        $valid = $this->_validate($options);

        $success = $validator->validate($this->get(), $options);
        $this->_errors = $validator->errors();
        return $success && $valid;
    }

    /**
     * Validates a relation.
     *
     * @param  array   $options Available options:
     *                          - `'embed'` _array_ : List of relations to validate.
     * @return boolean          Returns `true` if all validation rules on all fields succeed, otherwise `false`.
     */
    protected function _validate($options)
    {
        $defaults = ['embed' => true];
        $options += $defaults;

        if ($options['embed'] === true) {
            $options['embed'] = $this->hierarchy();
        }

        $schema = static::schema();
        $tree = $schema->treeify($options['embed']);
        $success = true;

        foreach ($tree as $field => $value) {
            if (isset($this->{$field})) {
                $rel = $schema->relation($field);
                $success = $success && $rel->validate($this, ['embed' => $value] + $options);
            }
        }
        return $success;
    }

    /**
     * Returns the errors from the last `->validate()` call.
     *
     * @return array The occured errors.
     */
    public function errors($options = [])
    {
        $defaults = ['embed' => true];
        $options += $defaults;

        if ($options['embed'] === true) {
            $options['embed'] = $this->hierarchy();
        }

        $schema = static::schema();
        $tree = $schema->treeify($options['embed']);
        $errors = $this->_errors;

        foreach ($tree as $field => $value) {
            if (isset($this->{$field})) {
                $errors[$field] = $this->{$field}->errors(['embed' => $value] + $options);
            }
        }
        return $errors;
    }

    /**
     * Returns all included relations accessible through this entity.
     *
     * @param  string $prefix The parent relation path.
     * @param  array  $ignore The already processed entities to ignore (address circular dependencies).
     * @return array          The included relations.
     */
    public function hierarchy($prefix = '', &$ignore = [])
    {
        $hash = spl_object_hash($this);
        if (isset($ignore[$hash])) {
            return false;
        } else {
            $ignore[$hash] = true;
        }

        $tree = array_fill_keys($this->schema()->relations(), true);
        $result = [];

        foreach ($tree as $field => $value) {
            if (!isset($this->{$field})) {
                continue;
            }
            $rel = $this->schema()->relation($field);
            if ($rel->type() === 'hasManyThrough') {
                $result[] = $prefix ? $prefix . '.' . $field : $field;
                continue;
            }
            if ($childs = $this->{$field}->hierarchy($field, $ignore)) {
                $result = array_merge($result, $childs);
            } elseif ($childs !== false) {
                $result[] = $prefix ? $prefix . '.' . $field : $field;
            }
        }
        return $result;
    }

    /**
     * Returns a string representation of the instance.
     *
     * @return string Returns the generated title of the object.
     */
    public function __toString()
    {
        return (string) $this->title();
    }

    /**
     * Converts the data in the record set to a different format, i.e. an array.
     *
     * @param string $format  Currently only `array`.
     * @param array  $options Options for converting:
     *                        - `'indexed'` _boolean_: Allows to control how converted data of nested collections
     *                        is keyed. When set to `true` will force indexed conversion of nested collection
     *                        data. By default `false` which will only index the root level.
     * @return mixed
     */
    public function to($format, $options = [])
    {
        $defaults = [
            'embed' => true,
            'verbose' => false,
            'rootPath' => null
        ];
        $options += $defaults;

        if ($options['embed'] === true) {
            $options['embed'] = $this->hierarchy();
        }

        $schema = $this->schema();
        $tree = $schema->treeify($options['embed']);
        $rootPath = $options['rootPath'];

        $result = [];
        $fields = array_keys($this->_data);
        if ($options['verbose'] && $schema->locked()) {
            $fields += array_keys($schema->fields());
        }
        foreach ($fields as $field) {
            if ($schema->hasRelation($field)) {
                $rel = $schema->relation($field);
                if (!$rel->embedded()) {
                    if (!array_key_exists($field, $tree)) {
                        continue;
                    }
                    $options['embed'] = $tree[$field];
                }
            }
            $value = $this[$field];
            if ($value instanceof Document) {
                $options['rootPath'] = $value->rootPath();
                $result[$field] = $value->to($format, $options);
            } elseif ($value instanceof Traversable) {
                $result[$field] = Collection::toArray($value, $options);
            } else {
                $options['rootPath'] = $rootPath ? $rootPath . '.' . $field : $field;
                $result[$field] = $schema->has($options['rootPath']) ? $schema->format($format, $options['rootPath'], $value, $options) : $value;
            }
        }
        return $result;
    }
}
