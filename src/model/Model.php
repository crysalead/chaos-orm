<?php
namespace chaos\model;

use set\Set;
use chaos\SourceException;

class Model implements \ArrayAccess, \Iterator
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected static $_classes = [];

    /**
     * Stores model's schema.
     *
     * @var array
     */
    protected static $_schemas = [];

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
     * Associative array of the model's fields and values.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Contains the values of updated fields. These values will be persisted to the backend data
     * store when the document is saved.
     *
     * @var array
     */
    protected $_updated = [];

    /**
     * The list of validation errors associated with this object, where keys are field names, and
     * values are arrays containing one or more validation error messages.
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * Creates a new record object with default values.
     *
     * @param array $config Possible options are:
     *                      - `'data'`   _array_  : The entiy class.
     *                      - `'parent'` _object_ : The parent instance.
     *                      - `'exists'` _boolean_: The class dependencies.
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'   => [],
            'exists' => false,
            'parent' => null,
            'rootPath'   => ''
        ];
        $config += $defaults;
        $this->_exists = $config['exists'];
        $this->_parent = $config['parent'];
        $this->rootPath = $config['rootPath'];
        $this->set($this->_data);
        $this->_data = $this->_updated;
    }

    /**
     * Get/set the parent.
     *
     * @param object $parent
     * @param array  $config
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
    public function key()
    {
        if (!$key = static::schema()->key()) {
            $class = get_called_class();
            throw new SourceException("No primary key has been defined for `{$class}`'s schema.");
        }
        return isset($this->$key) ? $this->$key : null;
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
        if ($id && $key = static::schema()->key()) {
            $data[$key] = $id;
        }
        $this->_data = $this->_updated = $data + $this->_updated;
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
     * {{{
     * [
     *     'id' => 3
     *     'comments' => [
     *         '5', '6', '9
     *     ]
     * ];
     * }}}
     *
     * To avoid painfull pre-processing, this function will automagically manage such relation
     * array by reformating it into the following on autoboxing:
     *
     * {{{
     * [
     *     'id' => 3
     *     'comments' => [
     *         ['id' => '5'],
     *         ['id' => '6'],
     *         ['id' => '9']
     *     ],
     * ];
     * }}}
     *
     * @param string $offset  The field name.
     * @param mixed  $data   The value.
     * @param array  $options An options array.
     */
    protected function _set($name, $data, $options)
    {
        $defaults = [
            'parent'   => $this,
            'rootPath'  => $this->_rootPath,
            'defaults' => !$this->_exists,
            'exists'   => $this->_exists
        ];
        $options += $defaults;

        $method = 'set' . ucwords(str_replace('_', ' ', $name));
        if (method_exists($this, $method)) {
            $data = $this->$method($name);
        }
        return $this->_updated[$name] = static::schema()->autobox($name, $data, $options);
    }

    /**
     * Returns the current data.
     *
     * @return array.
     */
    public function get($name = null)
    {
        if (!$name) {
            return $this->_updated;
        }
        $method = 'get' . ucwords(str_replace('_', ' ', $name));
        if (method_exists($this, $method)) {
            return  $this->$method($name);
        }
        if (array_key_exists($name, $this->_updated)) {
            return $this->_updated[$name];
        }
        if ($relation = static::relation($name)) {
            return $relation->get($this);
        }
        $null = null;
        return $null;
    }

    /**
     * Returns the previous data or a previous field value.
     *
     * @param  string $field A field name or `null` to retreive all data.
     * @return mixed
     */
    public function previous($field = null)
    {
        if (!$this->_exists) {
            return;
        }
        if (!$field) {
            return $this->_data;
        }
        return isset($this->_data[$field]) ? $this->_data[$field] : null;
    }

    /**
     * Gets the current state for a given field or, if no field is given, gets the array of modified fields.
     *
     * @param  string $field The field name to check its state.
     * @return array         Returns `true` if a field is given and was updated, `false` otherwise.
     *                       If no field is given returns an array of all modified fields and their
     *                       original values.
     */
    public function modified($field = null) {
        $result = [];
        $fields = $field ? [$field] : array_keys($this->_updated);

        foreach ($fields as $key) {
            if (!isset($this->_data[$key])) {
                $result[$key] = null;
                continue;
            }
            $modified = false;
            $value = $this->_updated[$key];
            if (method_exists($value, 'modified')) {
                $modified = $value->modified();
                $modified = $modified === true || (is_array($modified) && in_array(true, $modified, true));
            } elseif (is_object($value)) {
                $modified = $this->_data[$key] != $value;
            } else {
                $modified = $this->_data[$key] !== $value;
            }
            if ($modified) {
                $result[$key] = $this->_data[$key];
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
        return $this->get($offset);
    }

    /**
     * Allows to assign as an array, i.e. `$entity['id'] = $id`.
     *
     * @param  string $offset The field name.
     * @param  mixed  $value  The value.
     */
    public function offsetSet($offset, $value)
    {
        return $this->set($offset, $value);
    }

    /**
     * Allows test existance as an array, i.e. `isset($entity['id'])`.
     *
     * @param  string $offset The field name.
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_updated);
    }

    /**
     * Allows to unset as an array, i.e. `unset($entity['id'])`.
     *
     * @param  string $offset The field name.
     */
    public function offsetUnset($offset)
    {
        unset($this->_updated[$offset]);
    }

    /**
     * Overloading for reading inaccessible properties.
     *
     * @param  string $name Property name.
     * @return mixed        Result.
     */
    public function &__get($name)
    {
        return $this->get($name);
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
        $this->set($name, $value);
    }

    /**
     * Overloading for calling `isset()` or `empty()` on inaccessible properties.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function __isset($name)
    {
        return array_key_exists($offset, $this->_updated);
    }

    /**
     * Unset a property.
     *
     * @param string $name The name of the field to remove.
     */
    public function __unset($name)
    {
        unset($this->_updated[$offset]);
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
     * Returns a string representation of the instance.
     *
     * @return string Returns the generated title of the object.
     */
    public function __toString()
    {
        return (string) $this->title();
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
        if (!$with = $this->_with($options['with'])) {
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
     * Needs to be overrided using a database specific implementation.
     */
    public function save($options = [])
    {
        throw new SourceException('The `save()` method is not supported by `'. get_called_class() . '`.');
    }

    /**
     * Needs to be overrided using a database specific implementation.
     */
    public function delete($options = [])
    {
        throw new SourceException('The `delete()` method is not supported by `'. get_called_class() . '`.');
    }

    /**
     * Configures the model.
     *
     * @param array $config Possible options are:
     *                      - `'classes'` _array_: Dependencies array.
     */
    public static function config($config = [])
    {
        $defaults = [
            'classes' => [
                'schema' => 'chaos\model\Schema'
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_classes = $config['classes'];
    }

    /**
     * Instantiates a new record or document object, initialized with any data passed in. For example:
     *
     * {{{
     * $post = Posts::create(['title' => 'New post']);
     * echo $post->title; // echoes 'New post'
     * $success = $post->save();
     * }}}
     *
     * Note that while this method creates a new object, there is no effect on the database until
     * the `save()` method is called.
     *
     * In addition, this method can be used to simulate loading a pre-existing object from the
     * database, without actually querying the database:
     *
     * {{{
     * $post = Posts::create(['id' => $id, 'moreData' => 'foo'], ['exists' => true]);
     * $post->title = 'New title';
     * $success = $post->save();
     * }}}
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
            'class' => null
        ];
        $options += $defaults;
        $options['defaults'] = !$options['exists'];

        if ($options['defaults'] && $options['type'] === 'entity') {
            $data = Set::merge(Set::expand(static::schema()->defaults()), $data);
        }

        $class = isset($options['class']) ? $options['class'] : $this->_classes[$options['type']];

        $options = ['data' => $data] + $options;
        return new $class($options);
    }

    /**
     * Needs to be overrided using a database specific implementation.
     */
    public static function find($options = [])
    {
        throw new SourceException('The `find()` method is not supported by `'. get_called_class() . '`.');
    }

    /**
     * Needs to be overrided using a database specific implementation.
     */
    public static function update($data, $conditions = [], $options = [])
    {
        throw new SourceException('The `update()` method is not supported by `'. get_called_class() . '`.');
    }

    /**
     * Needs to be overrided using a database specific implementation.
     */
    public static function remove($conditions = [], $options = [])
    {
        throw new SourceException('The `remove()` method is not supported by `'. get_called_class() . '`.');
    }

    /**
     * Returns the schema of this instance.
     *
     * @return object
     */
    public static function schema()
    {
        $class = get_called_class();
        if (isset(static::$_schemas[$class])) {
            return static::$_schemas[$class];
        }
        $config += static::_meta() + ['classes' => ['entity' => $class]];
        $class = static::_classes['schema'];
        $schema = static::$_schemas[$class] = new $class($config);
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
         return static::schema()->relation($type);
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
     * {{{
     * $schema->add('id', ['type' => 'id']);
     *
     * $schema->add('title', ['type' => 'string', 'default' => true]);
     *
     * $schema->add('body', ['type' => 'string', 'use' => 'longtext']);
     *
     * // Dedicated class
     * $schema->add('comments', [
     *     'type' => 'object',
     *     'class' => 'name\space\model\Comment',
     *     'array' => true,
     *     'default' => []
     * ]);
     *
     * // Custom object
     * $schema->add('comments',       ['type' => 'object', 'array' => true, 'default' => []]);
     * $schema->add('comments.id',    ['type' => 'id']);
     * $schema->add('comments.email', ['type' => 'string']);
     * $schema->add('comments.body',  ['type' => 'string']);
     *
     * $schema->bind('tags', [
     *     'type'        => 'hasManyThrough',
     *     'through'     => 'post_tag'
     *     'using'       => 'tag'
     *     'constraints' => ['{:to}.enabled' => true]
     * ]);
     *
     * $schema->bind('post_tag', [
     *     'type'        => 'hasMany',
     *     'to'          => 'name\space\model\PostTag',
     *     'key'         => ['id' => 'post_id'],
     *     'constraints' => ['{:to}.enabled' => true]
     * ]);
     * }}}
     *
     */
    protected static function _schema($schema)
    {
    }

    /**
     * Reseting the model.
     */
    public static function reset()
    {
        static::$_classes = [];
        static::$_schemas = [];
    }
}
