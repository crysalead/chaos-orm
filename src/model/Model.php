<?php
namespace chaos\model;

use set\Set;
use chaos\SourceException;
use chaos\model\collection\Collection;

class Model implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected static $_classes = [
        'set'          => 'chaos\model\collection\Collection',
        'through'      => 'chaos\model\collection\Through',
        'conventions'  => 'chaos\model\Conventions'
    ];

    /**
     * Stores the default schema class dependency.
     *
     * @var array
     */
    protected static $_schema = 'chaos\model\Schema';

    /**
     * Stores model's schema.
     *
     * @var array
     */
    protected static $_schemas = [];

    /**
     * MUST BE re-defined in sub-classes which require a different connection.
     *
     * @var object The connection instance.
     */
    protected static $_connection = null;

    /**
     * Default query parameters for the model finders.
     *
     * @var array
     */
    protected static $_query = [];

    /**
     * MUST BE re-defined in sub-classes which require some different conventions.
     *
     * @var object A naming conventions.
     */
    protected static $_conventions = null;

    /**
     * If this record is chained off of another, contains the origin object.
     *
     * @var object
     */
    protected $_parent = null;

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
    protected $_loaded = [];

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

    /**************************
     *
     *  Model related methods
     *
     **************************/

    /**
     * Configures the Model.
     *
     * @param array $config Possible options are:
     *                      - `'classes'` _array_: Dependencies array.
     */
    public static function config($config = [])
    {
        $defaults = [
            'classes'     => static::$_classes,
            'schema'      => null,
            'connection'  => null,
            'conventions' => null
        ];
        $config = Set::merge($defaults, $config);

        if ($config['schema']) {
            static::$_schemas[static::class] = $config['schema'];
        } else {
            unset(static::$_schemas[static::class]);
        }
        static::$_classes = $config['classes'];
        static::$_connection = $config['connection'];
        static::$_conventions = $config['conventions'];
    }

    /**
     * (Method to override)
     * Return all meta-information for this class, including the name of the data source.
     *
     * Possible options are:
     * - `'key'`    _string_: The primary key or identifier key, i.e. `'id'`.
     * - `'source'` _string_: The name of the source to bind to (i.e the table or collection name).
     *
     * @var array
     */
    protected static function _meta()
    {
        return [];
    }

    /**
     * This function called once for initializing the model's schema.
     *
     * Example of schema initialization:
     * ```php
     * $schema->set('id', ['type' => 'id']);
     *
     * $schema->set('title', ['type' => 'string', 'default' => true]);
     *
     * $schema->set('body', ['type' => 'string', 'use' => 'longtext']);
     *
     * // Custom object
     * $schema->set('comments',       ['type' => 'object', 'array' => true, 'default' => []]);
     * $schema->set('comments.id',    ['type' => 'id']);
     * $schema->set('comments.email', ['type' => 'string']);
     * $schema->set('comments.body',  ['type' => 'string']);
     *
     * // Custom object with a dedicated class
     * $schema->set('comments', [
     *     'type' => 'object',
     *     'class' => 'name\space\model\Comment',
     *     'array' => true,
     *     'default' => []
     * ]);
     *
     * $schema->bind('tags', [
     *     'relation'    => 'hasManyThrough',
     *     'through'     => 'post_tag'
     *     'using'       => 'tag'
     *     'constraints' => ['{:to}.enabled' => true]
     * ]);
     *
     * $schema->bind('post_tag', [
     *     'relation'    => 'hasMany',
     *     'to'          => 'name\space\model\PostTag',
     *     'key'         => ['id' => 'post_id'],
     *     'constraints' => ['{:to}.enabled' => true]
     * ]);
     * ```
     *
     */
    protected static function _schema($schema)
    {
    }

    /**
     * Finds a record by its primary key.
     *
     * @param array  $options Options for the query.
     *                        -`'conditions'` : The conditions array.
     *                        - other options depend on the ones supported by the query instance.
     *
     * @return mixed          An instance of `Query`.
     */
    public static function find($options = [])
    {
        $schema = static::schema();
        $query = $schema->query();

        $options = Set::merge(static::$_query, $options);

        foreach ($options as $name => $value) {
            if (method_exists($query, $name)) {
                $query->{$name}($value);
            }
        }
        return $query;
    }

    /**
     * Finds the first record matching some conditions.
     *
     * @param  array $options      Options for the query.
     * @param  array $fetchOptions The fecthing options.
     * @return mixed               The result.
     */
    public static function first($options = [], $fetchOptions = [])
    {
        return static::find($options)->first($fetchOptions);
    }

    /**
     * Finds all records matching some conditions.
     *
     * @param  array $options      Options for the query.
     * @param  array $fetchOptions The fecthing options.
     * @return mixed               The result.
     */
    public static function all($options = [], $fetchOptions = [])
    {
        return static::find($options)->all($fetchOptions);
    }

    /**
     * Finds by id.
     *
     * @param  mixed $id           The id to retreive.
     * @param  array $fetchOptions The fecthing options.
     * @return mixed               The result.
     */
    public static function id($id, $fetchOptions = [])
    {
        $query = $this->schema->query();
        return $query->conditions([static::schema()->primaryKey() => $id])->first($fetchOptions);
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
     * @return object          Returns a new, un-saved record or document object. In addition to
     *                         the values passed to `$data`, the object will also contain any values
     *                         assigned to the `'default'` key of each field defined in the schema.
     */
    public static function create($data = [], $options = [])
    {
        $defaults = [
            'type' => 'entity',
            'exists' => false,
            'model' => static::class
        ];
        $options += $defaults;
        $options['defaults'] = !$options['exists'];

        if ($options['defaults'] && $options['type'] === 'entity') {
            $data = Set::merge(Set::expand(static::schema()->fields('default')), $data);
        }

        $type = $options['type'];
        $class = $type === 'entity' ? $options['model'] : static::$_classes[$options['type']];

        $options = ['data' => $data] + $options;
        return new $class($options);
    }

    /**
     * Gets/sets the connection object to which this model is bound.
     *
     * @param  object $connection The connection instance to set or `null` to get the current one.
     * @return object             Returns a connection instance.
     */
    public static function connection($connection = null)
    {
        if (func_num_args()) {
            static::$_connection = $connection;
        }
        return static::$_connection;
    }

    /**
     * Returns the schema of this instance.
     *
     * @return object
     */
    public static function schema()
    {
        $class = static::class;
        if (isset(static::$_schemas[$class])) {
            return static::$_schemas[$class];
        }
        $conventions = static::conventions();
        $config = static::_meta() + [
            'classes'     => ['entity' => $class] + static::$_classes,
            'connection'  => static::$_connection,
            'conventions' => $conventions,
            'model'       => static::class
        ];
        $class = static::$_schema;
        $schema = static::$_schemas[$class] = new $class($config);
        $schema->source = $conventions->apply('source', $config['classes']['entity']);
        static::_schema($schema);
        return $schema;
    }

    /**
     * Returns a relationship instance (shortcut).
     *
     * @param  string $name The name of a relation.
     * @return object       Returns a relationship intance or `null` if it doesn't exists.
     */
    public static function relation($name)
    {
         return static::schema()->relation($name);
    }

    /**
     * Returns a relationship instance (shortcut).
     *
     * @param  string  $name The name of a relation.
     * @return boolean       Returns `true` if the relation exists, `false` otherwise.
     */
    public static function hasRelation($name)
    {
         return static::schema()->hasRelation($name);
    }

    /**
     * Returns an array of relation names (shortcut).
     *
     * @param  string $type A relation type name.
     * @return array        Returns an array of relation names.
     */
    public static function relations($type = null)
    {
        return static::schema()->relations($type);
    }

    /**
     * Gets/sets the conventions object to which this model is bound.
     *
     * @param  object $conventions The conventions instance to set or `null` to get the current one.
     * @return object              Returns a connection instance.
     */
    public static function conventions($conventions = null)
    {
        if (func_num_args()) {
            static::$_conventions = $conventions;
        }
        if (!static::$_conventions) {
            $conventions = static::$_classes['conventions'];
            static::$_conventions = new $conventions();
        }
        return static::$_conventions;
    }

    /***************************
     *
     *  Entity related methods
     *
     ***************************/

    /**
     * Creates a new record object with default values.
     *
     * @param array $options Possible options are:
     *                      - `'data'`   _array_  : The entiy class.
     *                      - `'parent'` _object_ : The parent instance.
     *                      - `'exists'` _boolean_: The class dependencies.
     *
     */
    public function __construct($options = [])
    {
        $defaults = [
            'exists'   => false,
            'parent'   => null,
            'rootPath' => '',
            'data'     => []
        ];
        $options += $defaults;
        $this->_exists = $options['exists'];
        $this->_parent = $options['parent'];
        $this->_rootPath = $options['rootPath'];
        $this->_loaded = $options['data'];
        $this->set($this->_loaded);
        $this->_loaded = $this->_data;
    }

    /**
     * When not supported, delegate the call to the schema.
     *
     * @param  string $name   The name of the matcher.
     * @param  array  $params The parameters to pass to the matcher.
     * @return object         Returns `$this`.
     */
    public function __call($name, $params = [])
    {
        array_unshift($params, $this);
        return call_user_func_array([$this->schema(), $name], $params);
    }

    /**
     * A flag indicating whether or not this instance has been persisted somehow.
     *
     * @return boolean `True` if the record was read from or saved to the data-source, Otherwise `false`.
     */
    public function exists()
    {
        return $this->_exists;
    }

    /**
     * Gets/sets the parent.
     *
     * @param  object $parent The parent instance to set or `null` to get the current one.
     * @return object
     */
    public function parent($parent = null)
    {
        if ($parent === null) {
            return $this->_parent;
        }
        return $this->_parent = $parent;
    }

    /**
     * Get the base rootPath.
     *
     * @return string
     */
    public function rootPath()
    {
        return $this->_rootPath;
    }

    /**
     * If returns the key value.
     *
     * @return array     the primary key value.
     * @throws Exception Throws a `chaos\SourceException` if no primary key has been defined.
     */
    public function primaryKey()
    {
        if (!$key = static::schema()->primaryKey()) {
            $class = static::class;
            throw new SourceException("No primary key has been defined for `{$class}`'s schema.");
        }
        return isset($this->$key) ? $this->$key : null;
    }

    /**
     * Automatically called after an entity is saved. Updates the object's internal state
     * to reflect the corresponding database record.
     *
     * @param mixed $id      The ID to assign, where applicable.
     * @param array $data    Any additional generated data assigned to the object by the database.
     * @param array $options Method options:
     *                       - `'exists'` _boolean_: Determines whether or not this entity exists
     *                         in data store. Defaults to `null`.
     */
    public function sync($id = null, $data = [], $options = [])
    {
        if (isset($options['exists'])) {
            $this->_exists = $options['exists'];
        }
        if ($id && $pk = static::schema()->primaryKey()) {
            $data[$pk] = $id;
        }
        $this->_loaded = $this->_data = $data + $this->_data;
        return $this;
    }

    /**
     * Allows several properties to be assigned at once.
     *
     * @param array $data    An associative array of fields and values.
     * @param array $options An options array.
     */
    public function set($data = [], $options = [])
    {
        foreach ($data as $name => $value) {
            $this->_set($name, $value, $options);
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
     * @param mixed  $data    The value.
     * @param array  $options An options array.
     */
    protected function _set($name, $data, $options = [])
    {
        if (!$name) {
            throw new SourceException("Field name can't be empty.");
        }
        $defaults = [
            'parent'   => $this,
            'model'    => static::class,
            'rootPath' => $this->_rootPath,
            'defaults' => !$this->_exists,
            'exists'   => $this->_exists
        ];
        $options += $defaults;

        $method = 'set' . ucwords(str_replace('_', ' ', $name));
        if (method_exists($this, $method)) {
            $data = $this->$method($name);
        }
        return $this->_data[$name] = static::schema()->cast($name, $data, $options);
    }

    /**
     * Returns the current data.
     *
     * @return array.
     */
    public function get($name = null)
    {
        if (!$name) {
            return $this->_data;
        }
        $method = 'get' . ucwords(str_replace('_', ' ', $name));
        if (method_exists($this, $method)) {
            return $this->$method($name);
        }
        if (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        }
        if (static::hasRelation($name)) {
            return static::relation($name)->get($this);
        }
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
     * Access the data fields of the record.
     *
     * @param  string $options Options.
     * @return mixed Entire data array.
     */
    public function data($options = [])
    {
        return $this->to('array', $options);
    }

    /**
     * Returns the loaded data or a previous field value.
     *
     * @param  string $field A field name or `null` to retreive all data.
     * @return mixed
     */
    public function loaded($field = null)
    {
        if (!$field) {
            return $this->_loaded;
        }
        return isset($this->_loaded[$field]) ? $this->_loaded[$field] : null;
    }

    /**
     * Gets the current state for a given field or, if no field is given, gets the array of modified fields.
     *
     * @param  string $field The field name to check its state.
     * @return array         Returns `true` if a field is given and was updated, `false` otherwise.
     *                       If no field is given returns an array of all modified fields and their
     *                       original values.
     */
    public function modified($field = null)
    {
        $result = [];
        $fields = $field ? [$field] : array_keys($this->_data);

        foreach ($fields as $key) {
            if (!isset($this->_loaded[$key])) {
                if (isset($this->_data[$key])) {
                    $result[$key] = null;
                }
                continue;
            }
            $modified = false;
            $value = $this->_data[$key];
            if (method_exists($value, 'modified')) {
                $modified = !empty($value->modified());
            } elseif (is_object($value)) {
                $modified = $this->_loaded[$key] != $value;
            } else {
                $modified = $this->_loaded[$key] !== $value;
            }
            if ($modified) {
                $result[$key] = $this->_loaded[$key];
            }
        }
        return $field ? !empty($result) : array_keys($result);
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
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Allows to unset as an array, i.e. `unset($entity['id'])`.
     *
     * @param  string $offset The field name.
     */
    public function offsetUnset($offset)
    {
        unset($this->{$offset});
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
     * Alias to `::rewind()`.
     *
     * @return mixed The current item after rewinding.
     */
    public function reset()
    {
        return $this->rewind();
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
        if (!$name) {
            throw new SourceException("Field name can't be empty.");
        }
        $result = $this->get($name);
        return $result;
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
     * Unset a property.
     *
     * @param string $name The name of the field to remove.
     */
    public function __unset($name)
    {
        $this->_skipNext = $name === key($this->_data);
        unset($this->_data[$name]);
    }

    /**
     * Validates the instance datas.
     *
     * @param  array  $options Available options:
     *                         - `'rules'` _array_: If specified, this array will _replace_ the default
     *                           validation rules defined in `$validates`.
     *                         - `'events'` _mixed_: A string or array defining one or more validation
     *                           _events_. Events are different contexts in which data events can occur, and
     *                           correspond to the optional `'on'` key in validation rules. For example, by
     *                           default, `'events'` is set to either `'create'` or `'update'`, depending on
     *                           whether `$entity` already exists. Then, individual rules can specify
     *                           `'on' => 'create'` or `'on' => 'update'` to only be applied at certain times.
     *                           Using this parameter, you can set up custom events in your rules as well, such
     *                           as `'on' => 'login'`. Note that when defining validation rules, the `'on'` key
     *                           can also be an array of multiple events.
     * @return boolean           Returns `true` if all validation rules on all fields succeed, otherwise
     *                           `false`. After validation, the messages for any validation failures are assigned
     *                           to the entity, and accessible through the `errors()` method of the entity object.
     */
    public function validates()
    {
        $defaults = [
            'rules'  => static::model()->rules(),
            'events' => $this->exists() ? 'update' : 'create',
            'with'   => false
        ];
        $options += $defaults;
        $validator = $this->_classes['validator'];

        $this->_errors = [];

        if (!$this->_validates()) {
            return false;
        }

        $rules = $options['rules'];
        unset($options['rules']);

        if ($errors = $validator::check($this->data(), $rules, $options)) {
            $this->errors($errors);
        }
        return !$errors;
    }

    /**
     * Validates relationships.
     *
     * @return boolean
     */
    protected function _validates()
    {
        $relationship = static::$_classes['relationship'];
        if (!$with = $relationship::with($options['with'])) {
            return true;
        }
        foreach ($with as $field => $value) {
            $relation = static::relation($field);
            $errors = $relation->validates($this->$field, ['with' => $value] + $options);
            if (count($errors) !== count(array_filter($errors))) {
                return false;
            }
        }
        return true;
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
        switch ($format) {
            case 'array':
                $result = Collection::toArray($this->_data, $options);
            break;
            case 'string':
                $result = $this->__toString();
            break;
            default:
                $result = $this;
            break;
        }
        return $result;
    }
}
