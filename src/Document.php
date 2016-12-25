<?php
namespace Chaos\ORM;

use Traversable;
use ArrayAccess;
use Chaos\ORM\Contrat\DataStoreInterface;
use Chaos\ORM\Contrat\HasParentsInterface;

use Lead\Set\Set;
use Chaos\ORM\Collection\Collection;

class Document implements DataStoreInterface, HasParentsInterface, \ArrayAccess, \Iterator, \Countable
{
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
    protected static $_definition = 'Chaos\ORM\Schema';

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected static $_classes = [
        'collector'   => 'Chaos\ORM\Collector',
        'set'         => 'Chaos\ORM\Collection\Collection',
        'through'     => 'Chaos\ORM\Collection\Through',
        'conventions' => 'Chaos\ORM\Conventions'
    ];

    /**
     * Stores validator instances.
     *
     * @var array
     */
    protected static $_validators = [];

    /**
     * The collector instance.
     *
     * @var object
     */
    protected $_collector = null;

    /**
     * A reference to `Document`'s parents object.
     *
     * @var object
     */
    protected $_parents = null;

    /**
     * Stores the document schema.
     *
     * @var object
     */
    protected $_schema = null;

    /**
     * The UUID value.
     *
     * @var string
     */
    protected $_uuid = null;

    /**
     * If this instance has a parent, this value indicates the parent field path.
     *
     * @var string
     */
    protected $_basePath = '';

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
            'class'       => Document::class
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
     *                         - `'type'`  _string_ : can be `'entity'` or `'set'`. `'set'` is used if the passed data represent a collection
     *                         - `'class'` _string_ : the document class name to use to create entities.
     * @return object          Returns a new, un-saved record or document object. In addition to
     *                         the values passed to `$data`, the object will also contain any values
     *                         assigned to the `'default'` key of each field defined in the schema.
     */
    public static function create($data = [], $options = [])
    {
        $defaults = [
            'type'  => 'entity',
            'class' => static::class
        ];
        $options += $defaults;

        $type = $options['type'];

        if ($type === 'entity') {
            $classname = $options['class'];
        } else {
            $options['schema'] = static::definition();
            $classname = static::$_classes[$type];
        }
        $options = ['data' => $data] + $options;

        return new $classname($options);
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
     *                      - `'uuid'`       _object_ : The object UUID.
     *                      - `'schema'`     _object_ : The schema instance.
     *                      - `'basePath'`   _string_ : A dotted field names path (for embedded entities).
     *                      - `'defaults'`   _boolean_  Populates or not the fields default values.
     *                      - `'data'`       _array_  : The entity's data.
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'collector' => null,
            'uuid'      => null,
            'schema'    => null,
            'basePath'  => null,
            'defaults'  => true,
            'data'      => []
        ];
        $config += $defaults;
        $this->_parents = new Map();

        $this->collector($config['collector']);
        $this->basePath($config['basePath']);
        $this->schema($config['schema']);

        if ($config['defaults'] && !$config['basePath']) {
            $config['data'] = Set::merge($this->schema()->defaults(), $config['data']);
        }

        $this->set($config['data']);
        $this->_persisted = $this->_data;

        $this->uuid($config['uuid']);
    }

    /**
     * Gets the document name.
     *
     * @return string Returns the entity's document name.
     */
    public function self()
    {
        return static::class;
    }

    /**
     * Gets/sets the schema instance.
     *
     * @param  object schema The schema instance to set or none to get it.
     * @return mixed         The schema instance on get or `$this` otherwise.
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
      * Gets/sets the instance uuid.
      *
      * @param  string $uuid The uuid to set or none to get it.
      * @return mixed        The uuid on get or `this` otherwise.
      */
    public function uuid($uuid = null)
    {
        if (func_num_args()) {
            if ($this->_uuid === $uuid) {
                return $this;
            }

            $collector = $this->collector();
            if ($this->_uuid) {
                $collector->remove($this->_uuid);
            }
            $this->_uuid = $uuid;
            if ($this->_uuid) {
                $collector->set($this->uuid(), $this);
            }
            return $this;
        }
        if (!$this->_uuid) {
            $this->_uuid = spl_object_hash($this);
        }
        return $this->_uuid;
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
     * Get parents.
     *
     * @return DocumentMap Returns the parents map.
     */
    public function parents()
    {
        return $this->_parents;
    }

    /**
     * Set a parent.
     *
     * @param  object $parent The parent instance to set.
     * @param  string $from   The parent from field to set.
     * @return self
     */
    public function setParent($parent, $from)
    {
        $this->parents()->set($parent, $from);
        return $this;
    }

    /**
     * Unset a parent.
     *
     * @param  object $parent The parent instance to unset.
     * @return self
     */
    public function unsetParent($parent)
    {
        $parents = $this->parents();
        $parents->remove($parent);
        if ($parents->count() === 0) {
            $this->collector()->remove($this->uuid());
        }
        return $this;
    }

    /**
     * Disconnect the document from its graph (i.e parents).
     * Note: It has nothing to do with persistance
     *
     * @return self
     */
    public function disconnect()
    {
        $parents = $this->parents();
        foreach ($parents->keys() as $object) {
            $path = $parents->get($object);
            unset($object->{$path});
        }
        return $this;
    }

    /**
     * Gets/sets the basePath (embedded entities).
     *
     * @param  string $basePath The basePath value to set or `null` to get the current one.
     * @return mixed            Returns the basePath value on get or `$this` otherwise.
     */
    public function basePath($basePath = null)
    {
        if (!func_num_args()) {
            return $this->_basePath;
        }
        $this->_basePath = $basePath;
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
            $data = [];
            foreach ($this->_data as $key => $value) {
                $data[$key] = $this->{$key};
            }
            return $data;
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

        $fieldname = $this->basePath() ? $this->basePath() . '.' . $name : $name;
        $schema = $this->schema();
        $field = $schema->has($fieldname) ? $schema->column($fieldname) : [];

        if (!empty($field['getter'])) {
            $value = $field['getter']($this, array_key_exists($name, $this->_data) ? $this->_data[$name] : null, $name);
        } elseif (array_key_exists($name, $this->_data)) {
            return $this->_data[$name];
        } elseif ($this instanceof Model && $schema->hasRelation($fieldname)) {
            return $this->_data[$name] = $schema->relation($fieldname)->get($this);
        } elseif (isset($field['type']) && $field['type'] === 'object') {
            $value = [];
        } else {
            return;
        }

        $value = $schema->cast($name, $value, [
            'collector' => $this->collector(),
            'parent'    => $this,
            'basePath'  => $this->basePath(),
            'defaults'  => true
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

        $previous = isset($this->_data[$name]) ? $this->_data[$name] : null;
        $value = $this->schema()->cast($name, $data, [
            'collector' => $this->collector(),
            'parent'    => $this,
            'basePath'  => $this->basePath(),
            'defaults'  => true
        ]);
        if ($previous !== null && $previous === $value) {
            return;
        }
        $fieldname = $this->basePath() ? $this->basePath() . '.' . $name : $name;
        $this->_data[$name] = $value;

        if ($schema->isVirtual($fieldname)) {
            return;
        }

        if ($value instanceof HasParentsInterface) {
            $value->setParent($this, $name);
        }
        if ($previous instanceof HasParentsInterface) {
            $previous->unsetParent($this);
        }
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

        $name = array_shift($keys);
        if ($keys) {
            $value = $this->get($name);
            if ($value instanceof ArrayAccess) {
                return $value->offsetExists($keys);
            }
            return false;
        }
        return array_key_exists($name, $this->_data);
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

        $name = array_shift($keys);
        if ($keys) {
            $value = $this->get($name);
            if ($value instanceof ArrayAccess) {
                $value->offsetUnset($keys);
            }
            return;
        }
        if (!array_key_exists($name, $this->_data)) {
            return;
        }
        $value = $this->_data[$name];
        if ($value instanceof HasParentsInterface) {
            $value->unsetParent($this);
        }
        $this->_skipNext = $name === key($this->_data);
        unset($this->_data[$name]);
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
     * Alias to `offsetExists()`.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function has($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * Overloading for calling `isset()` or `empty()` on inaccessible properties.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function __isset($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * Alias to `offsetUnset()`.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function remove($name)
    {
        $this->offsetUnset($name);
    }

    /**
     * Unsets a property.
     *
     * @param string $name The name of the field to remove.
     */
    public function __unset($name)
    {
        $this->offsetUnset($name);
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
        }
        $ignore[$hash] = true;

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
            'basePath' => null
        ];
        $options += $defaults;

        if ($options['embed'] === true) {
            $options['embed'] = $this->hierarchy();
        }

        $schema = $this->schema();

        $tree = $schema->treeify($options['embed']);
        $basePath = $options['basePath'];

        $result = [];

        if ($schema->locked()) {
            $fields = array_merge($schema->fields($options['basePath']), $schema->relations());
        } else {
            $fields = array_keys($this->_data);
        }

        foreach ($fields as $field) {
            $path = $basePath ? $basePath . '.' . $field : $field;
            $rel = null;
            if ($schema->hasRelation($path)) {
                $rel = $schema->relation($path);
                if (!$rel->embedded()) {
                    if (!array_key_exists($field, $tree)) {
                        continue;
                    }
                    $options['embed'] = $tree[$field];
                }
            }
            if (!$this->has($field)) {
                continue;
            }
            $value = $this[$field];
            if ($value instanceof Document) {
                $options['basePath'] = $rel && $rel->embedded() ? $value->basePath() : '';
                $result[$field] = $value->to($format, $options);
            } elseif ($value instanceof Traversable) {
                $options['basePath'] = $rel && $rel->embedded() ? $value->basePath() : '';
                $result[$field] = Collection::toArray($value, $options);
            } else {
                $options['basePath'] = $path;
                $result[$field] = $schema->has($options['basePath']) ? $schema->format($format, $options['basePath'], $value) : $value;
            }
        }
        return $result;
    }
}
