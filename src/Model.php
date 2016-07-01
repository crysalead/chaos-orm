<?php
namespace Chaos;

use Iterator;
use Lead\Set\Set;
use Chaos\Document;
use Chaos\collection\Collection;

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
    protected static $_definition = 'Chaos\Schema';

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
        'finders'     => 'Chaos\Finders',
        'validator'   => 'Lead\Validator\Validator'
    ];

    /**
     * Stores model's schema definition.
     *
     * @var array
     */
    protected static $_definitions = [];

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
     * Configures the Model.
     *
     * @param array $config Possible options are:
     *                      - `'classes'`     _array_ : The classes dependency array.
     *                      - `'schema'`      _object_: The schema instance to use.
     *                      - `'validator'`   _object_: The validator instance to use.
     *                      - `'finders'`     _object_: The finders instance to use.
     *                      - `'connection'`  _object_: The connection instance to use.
     *                      - `'conventions'` _object_: The conventions instance to use.
     */
    public static function config($config = [])
    {
        $defaults = [
            'classes'     => static::$_classes,
            'schema'      => null,
            'validator'   => null,
            'finders'     => null,
            'query'       => [],
            'connection'  => null,
            'conventions' => null
        ];
        $config = Set::merge($defaults, $config);

        static::$_classes = $config['classes'];

        static::conventions($config['conventions']);
        static::connection($config['connection']);

        static::definition($config['schema']);
        static::validator($config['validator']);
        static::finders($config['finders']);
        static::query($config['query']);
    }

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
            'classes'     => ['entity' => $self] + static::$_classes,
            'connection'  => static::$_connection,
            'conventions' => $conventions,
            'model'       => $self
        ];
        $config += ['source' => $conventions->apply('source', $config['classes']['entity'])];

        $class = static::$_definition;
        $schema = static::$_definitions[$self] = new $class($config);
        static::_define($schema);
        return $schema;
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
     *     'type'  => 'object',
     *     'model' => 'name\space\model\Comment',
     *     'array' => true,
     *     'default' => []
     * ]);
     *
     * $schema->bind('tags', [
     *     'relation'    => 'hasManyThrough',
     *     'through'     => 'post_tag',
     *     'using'       => 'tag'
     * ]);
     *
     * $schema->bind('post_tag', [
     *     'relation'    => 'hasMany',
     *     'to'          => 'name\space\model\PostTag',
     *     'key'         => ['id' => 'post_id']
     * ]);
     * ```
     *
     * @param object $schema The schema instance.
     */
    protected static function _define($schema)
    {
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
        $options = Set::merge(static::query(), $options);
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
     * Resets the Model.
     */
    public static function reset()
    {
        static::config();
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
     *                      - `'autoreload'` _boolean_: If `true` and exists is `null`, autoreload the entity
     *                                                  from the datasource
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'exists'     => false,
            'autoreload' => true,
            'data'       => []
        ];
        $config += $defaults;
        parent::__construct($config);

        /**
         * Cached value indicating whether or not this instance exists somehow. If this instance has been loaded
         * from the database, or has been created and subsequently saved this value should be automatically
         * setted to `true`.
         *
         * @var Boolean
         */
        $this->exists($config['exists']);

        if ($this->exists() === false) {
            return;
        }

        if ($this->exists() !== true) {
            if ($config['autoreload']) {
                $this->reload();
            }
            $this->set($config['data']);
        }

        $this->_persisted = $this->_data;

        if ($this->exists() !== true) {
            return;
        }

        if (!$id = $this->id()) {
          throw new ChaosException("Existing entities must have a valid ID.");
        }
        $source = $this->schema()->source();
        $this->uuid($source . ':' . $id);
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
     * @throws Exception Throws a `ChaosException` if no primary key has been defined.
     */
    public function id()
    {
        if (!$key = $this->schema()->key()) {
            $class = static::class;
            throw new ChaosException("No primary key has been defined for `{$class}`'s schema.");
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
        if (!func_num_args()) {
            return $this->_exists;
        }
        $this->_exists = $exists;
        return $this;
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
        if ($id && $key = $this->schema()->key()) {
            $data[$key] = $id;
        }
        $this->set($data + $this->_data);
        $this->_persisted = $this->_data;
        return $this;
    }

    /**
     * Creates and/or updates an entity and its direct relationship data in the datasource.
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
        $defaults = ['validate' => true];
        $options += $defaults;
        if ($options['validate'] && !$this->validate($options)) {
            return false;
        }
        $schema = $this->schema();
        return $schema->save($this, $options);
    }

    /**
     * Similar to `->save()` except the direct relationship has not been saved by default.
     *
     * @param  array   $options Same options as `->save()`.
     * @return boolean          Returns `true` on a successful save operation, `false` on failure.
     */
    public function persist($options = [])
    {
        return $this->save($options + ['embed' => false]);
    }

    /**
     * Reloads the entity from the datasource.
     */
    public function reload()
    {
        $id = $this->id();
        $persisted = $id !== null ? static::load($id) : null;
        if (!$persisted) {
            throw new ChaosException("The entity id:`{$id}` doesn't exists.");
        }
        $this->_exists = true;
        $this->set($persisted->get());
        $this->_persisted = $this->_data;
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
        $schema = $this->schema();
        if ((!$key = $schema->key()) || $this->exists() === false) {
            return false;
        }
        if($schema->delete([$key => $this->id()])) {
            $this->_exists = false;
            $this->_persisted = [];
            return true;
        }
        return false;
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
     * Returns a string representation of the instance.
     *
     * @return string Returns the generated title of the object.
     */
    public function __toString()
    {
        return (string) $this->title();
    }
}
