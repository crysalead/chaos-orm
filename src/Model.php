<?php
namespace Chaos\ORM;

use Exception;
use Iterator;
use Lead\Set\Set;
use Chaos\ORM\Map;
use Chaos\ORM\Document;
use Chaos\ORM\collection\Collection;

class Model extends Document
{
    /**
     * MUST BE re-defined in sub-classes which require some different conventions.
     *
     * @var object A naming conventions.
     */
    protected static $_conventions = null;

    /**
     * MUST BE re-defined in sub-classes which require a different connection.
     *
     * @var object The connection instance.
     */
    protected static $_connection = null;

    /**
     * MUST BE re-defined in sub-classes which require a different schema.
     *
     * @var string
     */
    protected static $_definition = 'Chaos\ORM\Schema';

    /**
     * MUST BE re-defined in sub-classes which require a different unicity mode by default.
     *
     * @var boolean
     */
    protected static $_unicity = false;

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected static $_classes = [
        'collector'   => 'Chaos\ORM\Collector',
        'set'         => 'Chaos\ORM\Collection\Collection',
        'through'     => 'Chaos\ORM\Collection\Through',
        'conventions' => 'Chaos\ORM\Conventions',
        'finders'     => 'Chaos\ORM\Finders',
        'validator'   => 'Lead\Validator\Validator'
    ];

    /**
     * Stores model's schema definition.
     *
     * @var array
     */
    protected static $_definitions = [];

    /**
     * Stores model's unicity definition.
     *
     * @var boolean
     */
    protected static $_unicities = [];

    /**
     * Source of truths when unicity is enabled.
     *
     * @var array
     */
    protected static $_shards = [];

    /**
     * Stores finders instances.
     *
     * @var array
     */
    protected static $_finders = [];

    /**
     * Default query parameters for the model finders.
     *
     * @var array
     */
    protected static $_query = [];

    /**************************
     *
     *  Model related methods
     *
     **************************/

    /**
     * Gets/sets the schema instance.
     *
     * @param  object $schema The schema instance to set or none to get it.
     * @return mixed          The schema instance on get.
     */
    public static function definition($schema = null)
    {
        if (func_num_args()) {
            if (is_string($schema)) {
                static::$_definition = $schema;
            } elseif($schema) {
                static::$_definitions[static::class] = $schema;
            } else {
                unset(static::$_definitions[static::class]);
            }
            return;
        }
        $self = static::class;
        if (isset(static::$_definitions[$self])) {
            return static::$_definitions[$self];
        }
        $conventions = static::conventions();
        $config = [
            'connection'  => static::$_connection,
            'conventions' => $conventions,
            'class'       => $self
        ];
        $config += ['source' => $conventions->apply('source', $self)];

        $class = static::$_definition;
        $schema = static::$_definitions[$self] = new $class($config);
        static::_define($schema);
        return $schema;
    }

    /**
     * Get/set the unicity value.
     *
     * @param  boolean|null $enable The unicity value or none to get it.
     * @return boolean|self         The unicity value on get and `this` on set.
     */
    static function unicity($enable = null) {
       if (!func_num_args()) {
         return isset(static::$_unicities[static::class]) ? static::$_unicities[static::class] : static::$_unicity;
       }
       static::$_unicities[static::class] = !!$enable;
    }

    /**
     * Get the shard attached to the model.
     *
     * @param  Map $collector The collector instance to set or none to get it.
     * @return Map            The collector instance on get and `this` on set.
     */
    static function shard($collector = null) {
      if (func_num_args()) {
        if ($collector) {
          static::$_shards[static::class] = $collector;
        } else {
          unset(static::$_shards[static::class]);
        }
        return;
      }
      if (!isset(static::$_shards[static::class])) {
        static::$_shards[static::class] = new Map();
      }
      return static::$_shards[static::class];
    }

    /**
     * This function is called once for initializing the validator instance.
     *
     * @param object $validator The validator instance.
     */
    protected static function _rules($validator)
    {
    }

    /**
     * This function is called once for initializing finders.
     *
     * @param object $validator The validator instance.
     */
    protected static function _finders($finders)
    {
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
     *                         - `'type'`   _string_  : can be `'entity'` or `'set'`. `'set'` is used if the passed data represent a collection
     *                         - `'class'`  _string_  : the document class name to use to create entities.
     *                         - `'exists'` _boolean_ : the persitance boolean.
     * @return object          Returns a new, un-saved record or document object. In addition to
     *                         the values passed to `$data`, the object will also contain any values
     *                         assigned to the `'default'` key of each field defined in the schema.
     */
    public static function create($data = [], $options = [])
    {
        $defaults = [
            'type'   => 'entity',
            'class'  => static::class,
            'exists' => false
        ];
        $options += $defaults;

        $type = $options['type'];
        $classname = $options['class'];

        if ($type === 'entity' && $options['exists'] !== false && $classname::unicity()) {
            $data = $data ? $data : [];
            $schema = $classname::definition();
            $shard = $classname::shard();
            $key = $schema->key();
            if (isset($data[$key]) && $shard->has($data[$key])) {
                $id = $data[$key];
                $instance = $shard->get($id);
                $instance->amend($data, ['exists' => $options['exists'], 'rebuild' => true]);
                return $instance;
            }
        }
        return parent::create($data, $options);
    }

    /**
     * Finds a record by its primary key.
     *
     * @param  array  $options Options for the query.
     *                         -`'conditions'` : The conditions array.
     *                         - other options depend on the ones supported by the query instance.
     *
     * @return object          An instance of `Query`.
     */
    public static function find($options = [])
    {
        $options = Set::extend(static::query(), $options);
        $schema = static::definition();
        return $schema->query(['query' => $options] + ['finders' => static::finders()]);
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
     * Finds a record by its ID.
     *
     * @param  mixed $id           The id to retreive.
     * @param  array $fetchOptions The fecthing options.
     * @return mixed               The result.
     */
    public static function load($id, $options = [], $fetchOptions = [])
    {
        $options = ['conditions' => [static::definition()->key() => $id]] + $options;
        return static::find($options)->first($fetchOptions);
    }

    /**
     * Gets/sets the connection object to which this model is bound.
     *
     * @param  object $connection The connection instance to set or `null` to get the current one.
     * @return mixed              Returns a connection instance on get.
     */
    public static function connection($connection = null)
    {
        if (func_num_args()) {
            static::$_connection = $connection;
            unset(static::$_definitions[static::class]);
            return;
        }
        return static::$_connection;
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

    /**
     * Gets/sets the default query parameters used on finds.
     *
     * @param  array $query The query parameters.
     * @return array        Returns the default query parameters.
     */
    public static function query($query = [])
    {
        if (func_num_args()) {
            static::$_query[static::class] = is_array($query) ? $query : [];
        }
        return isset(static::$_query[static::class]) ? static::$_query[static::class] : [];
    }

    /**
     * Gets/sets the finders instance.
     *
     * @param  object $finders The finders instance to set or none to get it.
     * @return mixed           The finders instance on get.
     */
    public static function finders($finders = null)
    {
        if (func_num_args()) {
            static::$_finders[static::class] = $finders;
            return;
        }
        $self = static::class;
        if (isset(static::$_finders[$self])) {
            return static::$_finders[$self];
        }
        $class = static::$_classes['finders'];
        $finders = static::$_finders[$self] = new $class();
        static::_finders($finders);
        return $finders;
    }

    /**
     * Reset the Model class.
     */
    public static function reset()
    {
        unset(static::$_dependencies[static::class]);
        static::conventions(null);
        static::connection(null);
        static::definition(null);
        static::validator(null);
        static::query([]);
        unset(static::$_unicities[static::class]);
        unset(static::$_definitions[static::class]);
        unset(static::$_shards[static::class]);
    }

    /***************************
     *
     *  Entity related methods
     *
     ***************************/

    /**
     * Creates a new record object with default values.
     *
     * @param array $config Possible options are:
     *                      - `'exists'`     _boolean_: A boolean or `null` indicating if the entity exists.
     *                                                  from the datasource
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'exists' => false
        ];
        $config += $defaults;
        unset($config['basePath']);

        $this->exists($config['exists']);
        parent::__construct($config);

        $this->_exists = $this->_exists === 'all' ? true : $this->_exists;

        if ($this->_exists !== true) {
            return;
        }

        if (!$id = $this->id()) {
            throw new ORMException("Existing entities must have a valid ID.");
        }

        if (!static::unicity()) {
            return;
        }
        $shard = static::shard();
        if ($shard->has($id)) {
          $schema = static::definition();
          $source = $schema->source();
          throw new ORMException("Trying to create a duplicate of `" . $source . "` ID `" . $id . "` which is not allowed when unicity is enabled.");
        }
        $shard->set($id, $this);
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
     * Returns the primary key value.
     *
     * @return array     the primary key value.
     * @throws Exception Throws a `ORMException` if no primary key has been defined.
     */
    public function id()
    {
        if (!$key = $this->schema()->key()) {
            $class = static::class;
            throw new ORMException("No primary key has been defined for `{$class}`'s schema.");
        }
        return $this->{$key};
    }

    /**
     * Gets/sets whether or not this instance has been persisted somehow.
     *
     * @param  boolean $exists The exists value to set or `null` to get the current one.
     * @return mixed           Returns the exists value on get or `$this` otherwise.
     */
    public function exists($exists = null)
    {
        if (func_num_args()) {
            $this->_exists = $exists;
            return $this;
        }
        if ($this->_exists === null) {
            throw new ORMException("No persitance information is available for this entity use `sync()` to get an accurate existence value.");
        }
        return $this->_exists;
    }

    /**
     * Allows fields to be accessed as an array, i.e. `$entity['id']`.
     *
     * @param  string $offset The field name.
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        $result = $this->fetch($offset);
        return $result;
    }

    /**
     * Overloading for reading inaccessible properties.
     *
     * @param  string $name Property name.
     * @return mixed        Result.
     */
    public function &__get($name)
    {
        $value = $this->fetch($name);
        return $value;
    }

    /**
     * Lazy load a relation and return its data.
     *
     * @param  string name The name of the relation to load.
     * @return mixed.
     */
    public function fetch($name)
    {
        return $this->get($name, function($instance, $name) {
            $collection = [$instance];
            $this->schema()->embed($collection, $name);
            return isset($this->_data[$name]) ? $this->_data[$name] : null;
        });
    }

    /**
     * Automatically called after an entity is saved. Updates the object's internal state
     * to reflect the corresponding database record.
     *
     * @param array $data    Any additional generated data assigned to the object by the database.
     * @param array $options Method options:
     *                       - `'exists'` _boolean_: Determines whether or not this entity exists
     *                         in data store. Defaults to `null`.
     */
    public function amend($data = [], $options = [])
    {
        $this->_exists = isset($options['exists']) ? $options['exists'] : $this->_exists;

        $previousId = $this->id();
        $schema = $this->schema();

        foreach ($data as $key => $value) {
            if (!empty($options['rebuild']) || !$this->has($key) || !$schema->hasRelation($key, false)) {
                $this->set($key, $value);
            } else {
                $this->get($key)->amend($value, $options);
            }
        }
        parent::amend();

        $this->_exists = $this->_exists === 'all' ? true : $this->_exists;

        if (!static::unicity()) {
          return $this;
        }

        $id = $this->id();

        if ($previousId !== null && $previousId !== $id) {
            static::shard()->delete($previousId);
        }

        if ($id !== null) {
            if ($this->_exists) {
                static::shard()->set($id, $this);
            } else {
                static::shard()->delete($id);
            }
        }
        return $this;
    }

    /**
     * Creates and/or updates an entity and its relationships data in the datasource.
     *
     * For example, to create a new record or document:
     * {{{
     * $post = Post::create(); // Creates a new object, which doesn't exist in the database yet
     * $post->title = "My post";
     * $success = $post->save();
     * }}}
     *
     * It is also used to update existing database objects, as in the following:
     * {{{
     * $post = Post::first($id);
     * $post->title = "Revised title";
     * $success = $post->save();
     * }}}
     *
     * To override the validation checks and save anyway, you can pass the `'validate'` option:
     *
     * {{{
     * $post->title = "We Don't Need No Stinkin' Validation";
     * $post->body = "I know what I'm doing.";
     * $post->save(['validate' => false]);
     * }}}
     *
     * @param  array    $options Options:
     *                           - `'validate'`  _boolean_: If `false`, validation will be skipped, and the record will
     *                                                      be immediately saved. Defaults to `true`.
     * @return boolean           Returns `true` on a successful save operation, `false` otherwise.
     */
    public function save($options = [])
    {
        $defaults = [
            'validate' => true,
            'embed'    => false
        ];

        $options += $defaults;
        if ($options['validate'] && !$this->validates($options)) {
            return false;
        }
        $schema = $this->schema();
        return $schema->save($this, $options);
    }

    /**
     * Sync the entity existence from the database.
     *
     * @param boolean $data Indicate whether the data need to by synced or not.
     */
    public function sync($data = false)
    {
        if ($this->_exists !== null) {
            return;
        }
        $id = $this->id();
        if ($id !== null) {
            $persisted = static::load($id);
            if ($persisted && $data) {
                $this->amend($persisted->data(), ['exists' => true]);
            } else {
                $this->_exists = !!$persisted;
            }
        } else {
            $this->_exists = false;
        }
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @return boolean Success.
     * @filter
     */
    public function delete()
    {
        $schema = $this->schema();
        return $schema->delete($this);
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
    public function validates($options = [])
    {
        $this->sync();
        $exists = $this->exists();
        $defaults = [
            'events'   => $exists ? 'update' : 'create',
            'required' => $exists ? false : true,
            'entity'   => $this,
            'embed'    => true
        ];
        $options += $defaults;
        $validator = static::validator();

        $valid = $this->_validates($options);

        $success = $validator->validates($this->get(), $options);
        $this->_errors = [];
        $this->invalidate($validator->errors());
        return $success && $valid;
    }

    /**
     * Check if nested relations are valid.
     *
     * @param  array   $options Available options:
     *                          - `'embed'` _array_ : List of relations to validate.
     * @return boolean          Returns `true` if all validation rules on all fields succeed, `false` otherwise.
     */
    protected function _validates($options)
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
                $success = $success && $rel->validates($this, $value ? $value + $options : $options);
            }
        }
        return $success;
    }

    /**
     * Invalidate a field or an array of fields.
     *
     * @param  string|array $field  The field to invalidate of an array of fields with associated errors.
     * @param  string|array $errors The associated error message(s).
     * @return self
     */
    public function invalidate($field, $errors = [])
    {
        if (func_num_args() === 1) {
            foreach ($field as $key => $value) {
                $this->invalidate($key, $value);
            }
            return $this;
        }
        if ($errors) {
            $this->_errors[$field] = (array) $errors;
        }
        return $this;
    }

    /**
     * Return an indivitual error
     *
     * @param  string       $field The field name.
     * @param  string|array $all   Indicate whether all errors or simply the first one need to be returned.
     * @return string              Return an array of error messages or the first one (depending on `$all`) or
     *                             an empty string for no error.
     */
    public function error($field, $all = false)
    {
        if (!empty($this->_errors[$field])) {
            return $all ? $this->_errors[$field] : reset($this->_errors[$field]);
        }
        return '';
    }

    /**
     * Returns the errors from the last `->validates()` call.
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
            if (!isset($this->{$field})) {
                continue;
            }
            if ($err = $this->{$field}->errors($value ? $value + $options : $options)) {
                $errors[$field] = $err;
            }
        }
        return $errors;
    }

    /**
     * Check if the entity or a specific field errored
     *
     * @param  string  $field The field to check.
     * @return boolean
     */
    public function errored($field = null)
    {
        if (!func_num_args()) {
            return !!$this->_errors;
        }
        return isset($this->_errors[$field]);
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
}
