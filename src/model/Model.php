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
        'set'         => 'chaos\model\collection\Collection',
        'through'     => 'chaos\model\collection\Through',
        'conventions' => 'chaos\model\Conventions',
        'validator'   => 'validator\Validator'
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
     * Stores validator instances.
     *
     * @var array
     */
    protected static $_validators = [];

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
            'validator'   => null,
            'connection'  => null,
            'conventions' => null
        ];
        $config = Set::merge($defaults, $config);

        static::$_classes = $config['classes'];

        static::conventions($config['conventions']);
        static::connection($config['connection']);

        if ($config['schema']) {
            static::schema($config['schema']);
        }

        if ($config['validator']) {
            static::validator($config['validator']);
        }


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
     * @param object $schema The schema instance.
     */
    protected static function _schema($schema)
    {
    }

    /**
     * This function called once for initializing the validator instance.
     *
     * @param object $validator The validator instance.
     */
    protected static function _rules($validator)
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
    public static function id($id, $options = [], $fetchOptions = [])
    {
        $options['conditions'] = [static::schema()->primaryKey() => $id];
        return static::find($options)->first($fetchOptions);
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
            $data = Set::merge(Set::expand(static::schema()->defaults()), $data);
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
            unset(static::$_schemas[static::class]);
        }
        return static::$_connection;
    }

    /**
     * Gets/sets the default query parameters used on finds.
     *
     * @param  array $query The query parameters.
     * @return array        Returns the default query parameters.
     */
    public static function query($query = [])
    {
        if (func_num_args()) {
            static::$_query = is_array($query) ? $query : [];
        }
        return static::$_query;
    }

    /**
     * Returns the schema instance of this model.
     *
     * @return object
     */
    public static function schema($schema = null)
    {
        if (func_num_args()) {
            return static::$_schemas[static::class] = $schema;
        }
        $self = static::class;
        if (isset(static::$_schemas[$self])) {
            return static::$_schemas[$self];
        }
        $conventions = static::conventions();
        $config = [
            'classes'     => ['entity' => $self] + static::$_classes,
            'connection'  => static::$_connection,
            'conventions' => $conventions,
            'model'       => $self
        ];
        $config += ['source' => $conventions->apply('source', $config['classes']['entity'])];

        $class = static::$_schema;
        $schema = static::$_schemas[$self] = new $class($config);
        static::_schema($schema);
        return $schema;
    }

    /**
     * Returns the validator instance of this model.
     *
     * @return object
     */
    public static function validator($validator = null)
    {
        if (func_num_args()) {
            return static::$_validators[static::class] = $validator;
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
            'rootPath' => null,
            'data'     => [],
            'partial'  => true
        ];
        $options += $defaults;
        $this->_exists = $options['exists'];
        $this->_parent = $options['parent'];
        $this->_rootPath = $options['rootPath'];
        if (!$this->exists()) {
            $this->set($options['data']);
            return;
        }
        if (!$options['partial']) {
            $this->set($options['data']);
            $this->_persisted = $this->_data;
            return;
        }
        $schema = static::schema();
        $primaryKey = $schema->primaryKey();
        $id = isset($options['data'][$primaryKey]) ? $options['data'][$primaryKey] : null;
        $persisted = static::id($id);
        if (!$persisted) {
            throw new SourceException("The entity id:`{$id}` doesn't exists.");
        }
        $this->_persisted = $this->_data = $persisted->plain();
        $this->set($options['data']);
    }

    /**
     * Gets the model class name.
     *
     * @return string The fully namespaced model class name.
     */
    public function model()
    {
        return static::class;
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
        $this->set($data + $this->_data);
        $this->_persisted = $this->_data;
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
     * Gets the plain array value of the `Collection`.
     *
     * @return array Returns an "unboxed" array of the `Collection`'s value.
     */
    public function plain()
    {
        return $this->_data;
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
     * Returns the persisted data.
     *
     * @param  string $field A field name or `null` to retreive all data.
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
     * Gets the current state for a given field or, if no field is given, gets the array of modified fields.
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
        $schema = static::schema();

        $result = [];
        $fields = $field ? [$field] : array_keys($this->_data);

        foreach ($fields as $key) {
            if (!array_key_exists($key, $this->_data)) {
                continue;
            }
            if (!array_key_exists($key, $this->_persisted)) {
                $result[$key] = null;
                continue;
            }
            $modified = false;
            $value = array_key_exists($key, $this->_data) ? $this->_data[$key] : $this->_persisted[$key];
            if (method_exists($value, 'modified') && $schema->has($key)) {
                $modified = $value->modified();
            } elseif (is_object($value)) {
                $modified = $this->_persisted[$key] != $value;
            } else {
                $modified = $this->_persisted[$key] !== $value;
            }
            if ($modified) {
                $result[$key] = $this->_persisted[$key];
            }
        }
        if ($field && $field !== true) {
            return !empty($result);
        }
        $result = array_keys($result);
        $result = $field ? $result : !!$result;
        return $result;
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
     * An instance method (called on record and document objects) to create or update the record or
     * document in the database.
     *
     * For example, to create a new record or document:
     * {{{
     * $post = Posts::create(); // Creates a new object, which doesn't exist in the database yet
     * $post->title = "My post";
     * $success = $post->save();
     * }}}
     *
     * It is also used to update existing database objects, as in the following:
     * {{{
     * $post = Posts::first($id);
     * $post->title = "Revised title";
     * $success = $post->save();
     * }}}
     *
     * By default, an object's data will be checked against the validation rules of the model it is
     * bound to. Any validation errors that result can then be accessed through the `errors()`
     * method.
     *
     * {{{
     * if (!$post->save($someData)) {
     *     return array('errors' => $post->errors());
     * }
     * }}}
     *
     * To override the validation checks and save anyway, you can pass the `'validate'` option:
     *
     * {{{
     * $post->title = "We Don't Need No Stinkin' Validation";
     * $post->body = "I know what I'm doing.";
     * $post->save(null, array('validate' => false));
     * }}}
     *
     * @param array $options Options:
     *                       - `'whitelist'` _array_:   An array of fields that are allowed to
     *                          be saved to this record.
     *                       - `'locked'`    _boolean_: Lock data to the schema fields.
     *                       - `'with'`      _boolean_: List of relations to save.
     * @return boolean       Returns `true` on a successful save operation, `false` on failure.
     */
    public function save($options = [])
    {
        $schema = static::schema();

        $defaults = [
            'whitelist' => null,
            'locked' => $schema->locked(),
            'with' => false
        ];
        $options += $defaults;

        if (!$this->modified()) {
            return true;
        }

        if (!$this->_save('belongsTo', $options)) {
            return false;
        }

        if (($whitelist = $options['whitelist']) || $options['locked']) {
            $whitelist = $whitelist ?: array_keys($schema->fields());
        }

        $exclude = array_diff($schema->relations(), array_keys($schema->fields()));

        $values = array_diff_key($this->data(), array_fill_keys($exclude, true));

        if ($this->exists()) {
            $id = $this->primaryKey();
            $cursor = $schema->update($values, [$id => $schema->primaryKey()]);
        } else {
            $cursor = $schema->insert($values);
        }

        $result = !$cursor->error();

        if (!$this->exists()) {
            $id = $this->primaryKey() === null ? $schema->lastInsertId() : null;
            $this->sync($id, [], ['exists' => true]);
        }

        $hasRelations = ['hasManyThrough', 'hasMany', 'hasOne'];

        if (!$this->_save($hasRelations, $options)) {
            return false;
        }
        return $result;
    }

    /**
     * Save relations helper.
     *
     * @param array $types Type of relations to save.
     */
    protected function _save($types, $options = [])
    {
        $defaults = ['with' => false];
        $options += $defaults;

        if (!$with = $this->_with($options['with'])) {
            return true;
        }
        $schema = static::schema();

        $types = (array) $types;
        foreach ($types as $type) {
            foreach ($with as $relName => $value) {
                if (!($rel = $schema->relation($relName)) || $rel->type() !== $type) {
                    continue;
                }
                if (!$rel->save($this, ['with' => $value] + $options)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * The `'with'` option formatter function
     *
     * @return array The formatter with array
     */
    protected function _with($with)
    {
        if (!$with) {
            return [];
        }
        if ($with === true) {
            $with = array_fill_keys(static::schema()->relations(), true);
        } else {
            $with = Set::expand(Set::normalize((array) $with));
        }
        return $with;
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @param array $options Options.
     * @return boolean Success.
     * @filter
     */
    public function delete($options = [])
    {
        $schema = static::schema();
        if (!$id = $schema->primaryKey() || !$this->exists()) {
            return false;
        }
        return $schema->remove([$id => $this->primaryKey()]);
    }

    /**
     * Validates the instance datas.
     *
     * @param  array  $options Available options:
     *                         - `'events'` _mixed_: A string or array defining one or more validation
     *                           events. Events are different contexts in which data events can occur, and
     *                           correspond to the optional `'on'` key in validation rules. For example, by
     *                           default, `'events'` is set to either `'create'` or `'update'`, depending on
     *                           whether the entity already exists. Then, individual rules can specify
     *                           `'on' => 'create'` or `'on' => 'update'` to only be applied at certain times.
     *                           You can also set up custom events in your rules as well, such as `'on' => 'login'`.
     *                           Note that when defining validation rules, the `'on'` key can also be an array of
     *                           multiple events.
     * @return boolean           Returns `true` if all validation rules on all fields succeed, otherwise
     *                           `false`. After validation, the messages for any validation failures are assigned
     *                           to the entity, and accessible through the `errors()` method of the entity object.
     */
    public function validate($options = [])
    {
        $defaults = [
            'events'   => $this->exists() ? 'update' : 'create',
            'required' => $this->exists() ? false : true,
            'with'   => false
        ];
        $options += $defaults;
        $validator = static::validator();

        $valid = $this->_validate($options);

        $success = $validator->validate($this->data(), $options);
        $this->_errors = $validator->errors();
        return $success && $valid;
    }

    /**
     * Validates relationships.
     *
     * @param  array   $options Available options:
     * @return boolean          Returns `true` if all validation rules on all fields succeed, otherwise `false`.
     */
    protected function _validate($options)
    {
        if (!$with = $this->_with($options['with'])) {
            return true;
        }
        foreach ($with as $name => $value) {
            $relation = static::relation($name);
            $fieldname = $relation->name();
            if (isset($this->{$fieldname}) && !$this->{$fieldname}->validate(['with' => $value] + $options)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the errors from the last validate call.
     *
     * @return array The occured errors.
     */
    public function errors($options = [])
    {
        $defaults = ['with' => false];
        $options += $defaults;

        $with = $this->_with($options['with']);

        $errors = $this->_errors;

        foreach ($with as $name => $value) {
            $relation = static::relation($name);
            $fieldname = $relation->name();
            $errors[$fieldname] = $this->{$fieldname}->errors(['with' => $value] + $options);
        }

        return $errors;
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
                $result = Collection::toArray($this, $options);
            break;
            case 'string':
                $result = $this->__toString();
            break;
            default:
                throw new SourceException("Unsupported format `'{$format}'`.");
            break;
        }
        return $result;
    }
}
