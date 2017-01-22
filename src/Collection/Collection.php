<?php
namespace Chaos\ORM\Collection;

use Exception;
use ArrayAccess;
use Traversable;
use Chaos\ORM\Contrat\DataStoreInterface;
use Chaos\ORM\Contrat\HasParentsInterface;

use InvalidArgumentException;
use Chaos\ORM\ORMException;
use Chaos\ORM\Document;
use Chaos\ORM\Model;
use Chaos\ORM\Map;

/**
 * `Collection` provide context-specific features for working with sets of data persisted by a backend data store.
 */
class Collection implements DataStoreInterface, HasParentsInterface, \ArrayAccess, \Iterator, \Countable
{
    /**
     * Contains all exportable formats and their handler
     *
     * @var array
     */
    protected static $_formats = [];

    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'collector' => 'Chaos\ORM\Collector'
    ];

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
     * If this `Collection` instance has a parent document (see `$_parent`), this value indicates
     * the key name of the parent document that contains it.
     *
     * @var string
     */
    protected $_basePath = null;

    /**
     * Cached value indicating whether or not this instance exists somehow. If this instance has been loaded
     * from the database, or has been created and subsequently saved this value should be automatically
     * setted to `true`.
     *
     * @var boolean
     */
    protected $_exists = false;

    /**
     * The schema to which this collection is bound. This
     * is usually the schema that executed the query which created this object.
     *
     * @var object
     */
    protected $_schema = null;

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Contains an array of backend-specific meta datas (like pagination datas)
     *
     * @var array
     */
    protected $_meta = [];

    /**
     * Workaround to allow consistent `unset()` in `foreach`.
     *
     * However it'll lead to the following behavior:
     * {{{
     *   $collection = new Collection(['data' => ['1', '2', '3']]);
     *   unset($collection[0]);
     *   $collection->next();   // will returns 2 instead of 3
     * }}}
     */
    protected $_skipNext = false;

    /**
     * Creates a collection.
     *
     * @param array $config Possible options are:
     *                      - `'collector'` _object_ : A collector instance.
     *                      - `'parent'`    _object_ : The parent instance.
     *                      - `'schema'`    _object_ : The attached schema.
     *                      - `'basePath'`  _string_ : A dotted string field path.
     *                      - `'meta'`      _array_  : Some meta data.
     *                      - `'data'`      _array_  : The collection data.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'collector' => null,
            'schema'    => null,
            'basePath'  => null,
            'meta'      => [],
            'data'      => [],
            'exists'    => false,
            'index'     => null
        ];
        $config += $defaults;

        $this->collector($config['collector']);
        $this->exists($config['exists']);
        $this->basePath($config['basePath']);
        $this->schema($config['schema']);
        $this->meta($config['meta']);

        foreach ($config['data'] as $key => $value) {
            $this->set($key, $value);
        }
        $this->_parents = new Map();
    }

    /**
     * Gets/sets the collector.
     *
     * @param  object $collector The collector instance to set or none to get the current one.
     * @return object            Returns the collector instance on get or `$this` otherwise.
     */
    public function collector($collector = null)
    {
        if (func_num_args()) {
            $this->_collector = $collector;
            return $this;
        }
        if (!$this->_collector) {
            $collector = $this->_classes['collector'];
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
        $this->_parents->set($parent, $from);
        return $this;
    }

    /**
     * Unset a parent.
     *
     * @param  pbject $parent The parent instance to remove.
     * @return self
     */
    public function removeParent($parent)
    {
        $this->_parents->remove($parent);
        return $this;
    }

    /**
     * Disconnect the collection from its graph (i.e parents).
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
     * Gets/sets whether or not this instance has been persisted somehow.
     *
     * @param  boolean $exists The exists value to set or none to get the current one.
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
     * Gets/sets the schema instance.
     *
     * @param  object schema The schema instance to set or none to get it.
     * @return mixed         The schema instance or `$this` on set.
     */
    public function schema($schema = null) {
        if (!func_num_args()) {
            return $this->_schema;
        }
        $this->_schema = $schema;
        return $this;
    }

    /**
     * Gets/sets the basePath (embedded entities).
     *
     * @param  string $basePath The basePath value to set or none to get the current one.
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
     * Gets/sets the meta data.
     *
     * @param  string $meta The meta value to set or none to get the current one.
     * @return mixed        Returns the meta value on get or `$this` otherwise.
     */
    public function meta($meta = null)
    {
        if (!func_num_args()) {
            return $this->_meta;
        }
        $this->_meta = $meta;
        return $this;
    }

    /**
     * Handles dispatching of methods against all items in the collection.
     *
     * @param  string $method The name of the method to call on each instance in the collection.
     * @param  mixed  $params The parameters to pass on each method call.
     *
     * @return mixed          Returns either an array of the return values of the methods, or the
     *                        return values wrapped in a `Collection` instance.
     */
    public function invoke($method, $params = [])
    {
        $data = [];
        $isCallable = is_callable($params);

        foreach ($this as $key => $object) {
            $callParams = $isCallable ? $params($object, $key, $this) : $params;
            $data[$key] = call_user_func_array([$object, $method], $callParams);
        }

        return new static(compact('data'));
    }

    /**
     * Gets an `Entity` object.
     *
     * @param  integer $offset The offset.
     * @return mixed          Returns an `Entity` object if exists otherwise returns `undefined`.
     */
    public function get($offset = null)
    {
        if (!func_num_args()) {
            return $this->_data;
        }
        $keys = is_array($offset) ? $offset : explode('.', $offset);
        if (!$keys) {
            throw new ORMException("Invalid empty index `" . $offset . "` for collection.");
        }

        $name = array_shift($keys);
        if ($keys) {
            if (!array_key_exists($name, $this->_data)) {
                throw new ORMException("Missing index `" . $name . "` for collection.");
            }
            $value = $this->_data[$name];
            if (!$value instanceof DataStoreInterface) {
                throw new ORMException("The field: `" . $name . "` is not a valid document or entity.");
            }
            return $value->get($keys);
        }
        if (!array_key_exists($name, $this->_data)) {
            throw new ORMException("Missing index `" . $name . "` for collection.");
        }
        return $this->_data[$name];
    }

    /**
     * Sets data inside the `Collection` instance.
     *
     * @param  mixed $offset The offset.
     * @param  mixed $data   The entity object or data to set.
     * @return mixed         Returns `$this`.
     */
    public function set($offset = null, $data = [])
    {
        $keys = is_array($offset) ? $offset : ($offset !== null ? explode('.', $offset) : []);

        $name = array_shift($keys);

        if ($keys) {
          $this->get($name)->set($keys, $data);
        }

        if ($schema = $this->schema()) {
            $data = $schema->cast(null, $data, [
                'collector' => $this->collector(),
                'parent'    => $this,
                'basePath'  => $this->basePath(),
                'exists'    => $this->exists(),
                'defaults'  => true
            ]);
        }
        if ($name !== null) {
            if (!is_numeric($name)) {
                throw new ORMException("Invalid index `" . $name . "` for a collection, must be a numeric value.");
            }
            $previous = isset($this->_data[$name]) ? $this->_data[$name] : null;
            $this->_data[$name] = $data;
            if ($previous instanceof HasParentsInterface) {
                $previous->removeParent($this);
            }
        } else {
            $this->_data[] = $data;
            $name = key($this->_data);
        }
        if ($data instanceof HasParentsInterface) {
            $data->setParent($this, $name);
        }
        return $this;
    }

    /**
     * Gets an `Entity` object using PHP's array syntax, i.e. `$documents[3]` or `$records[5]`.
     *
     * @param  mixed $offset The offset.
     * @return mixed         Returns an `Entity` object if exists otherwise returns `null`.
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Adds the specified object to the `Collection` instance, and assigns associated metadata to
     * the added object.
     *
     * @param  string $offset The offset to assign the value to.
     * @param  mixed  $data   The entity object to add.
     * @return mixed          Returns the set `Entity` object.
     */
    public function offsetSet($offset, $data)
    {
        return $this->set($offset, $data);
    }

    /**
     * Returns a boolean indicating whether an offset exists for the current `Collection`.
     *
     * @param  string $offset String or integer indicating the offset or index of an entity in the set.
     * @return boolean        Result.
     */
    public function offsetExists($offset)
    {
        $keys = is_array($offset) ? $offset : explode('.', $offset);
        if (!$keys) {
            return false;
        }

        $name = array_shift($keys);

        if ($keys) {
            if (!array_key_exists($name, $this->_data)) {
                return false;
            }
            $value = $this->_data[$name];
            if ($value instanceof ArrayAccess) {
                return $value->offsetExists($keys);
            }
            return false;
        }
        return array_key_exists($name, $this->_data);
    }

    /**
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        $keys = is_array($offset) ? $offset : explode('.', $offset);
        if (!$keys) {
            return;
        }

        $name = array_shift($keys);

        if ($keys) {
            if (!array_key_exists($name, $this->_data)) {
                return false;
            }
            $value = $this->_data[$name];
            if ($value instanceof ArrayAccess) {
                $value->offsetUnset($keys);
            }
            return;
        }
        $this->_skipNext = (integer) $name === key($this->_data);

        if (!array_key_exists($name, $this->_data)) {
            return;
        }

        $value = $this->_data[$name];
        unset($this->_data[$name]);
        if ($value instanceof HasParentsInterface) {
            $value->removeParent($this);
        }
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
     * Alias to `offsetUnset()`.
     *
     * @param  string  $name Property name.
     * @return boolean
     */
    public function remove($name)
    {
        return $this->offsetUnset($name);
    }

    /**
     * Merges another collection to this collection.
     *
     * @param  mixed   $collection   A collection.
     * @param  boolean $preserveKeys If `true` use the key value as a hash to avoid duplicates.
     *
     * @return object                Return the merged collection.
     */
    public function merge($collection, $preserveKeys = false)
    {
        foreach($collection as $key => $value) {
            $preserveKeys ? $this[$key] = $value : $this[] = $value;
        }
        return $this;
    }

    /**
     * Clear the collection
     *
     * @return self This collection instance.
     */
    public function clear()
    {
        $this->_data = [];
        return $this;
    }

    /**
     * Returns the item keys.
     *
     * @return array The keys of the items.
     */
    public function keys()
    {
        return array_keys($this->_data);
    }

    /**
     * Returns the currently pointed to record's unique key.
     *
     * @param  boolean $full If true, returns the complete key.
     * @return mixed
     */
    public function key($full = false)
    {
        return key($this->_data);
    }

    /**
     * Returns the currently pointed to record in the set.
     *
     * @return object `Record`
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
     * Returns the next document in the set, and advances the object's internal pointer. If the end
     * of the set is reached, a new document will be fetched from the data source connection handle
     * If no more documents can be fetched, returns `null`.
     *
     * @return mixed Returns the next document in the set, or `false`, if no more documents are
     *               available.
     */
    public function next()
    {
        $value = $this->_skipNext ? current($this->_data) : next($this->_data);
        $this->_skipNext = false;
        return key($this->_data) !== null ? $value : null;
    }

    /**
     * Rewinds the collection to the beginning.
     */
    public function rewind()
    {
        $this->_skipNext = false;
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
    public function count()
    {
        return count($this->_data);
    }

    /**
     * Return the collection indexed by an arbitrary field name.
     *
     * @param  string  $field   The field name to use for indexing
     * @param  boolean $byValue If `true` returns the documents instead of thrir index number in the collection.
     * @return array            The indexed array
     */
    public function indexBy($field, $byValue = false)
    {
        $indexes = [];
        foreach ($this as $key => $document) {
            if (!$document instanceof Document) {
                throw new Exception("Only document can be indexed.");
            }
            $index = $document[$field];
            if (!isset($indexes[$index])) {
                $indexes[$index] = [];
            }
            $indexes[$index][] = $byValue ? $document : $key;
        }
        return $indexes;
    }

    /**
     * Find the index of an entity with a defined id.
     *
     * @param  mixed        $id The entity id to look for.
     * @return integer|null     The entity's index number in the collection or `null` if not found.
     */
    public function indexOfId($id)
    {
        $id = (string) $id;
        foreach ($this as $key => $entity) {
            if (!$entity instanceof Model) {
                throw new Exception('Error, `indexOfId()` is only available on models.');
            }
            if ($id === (string) $entity->id()) {
                return $key;
            }
        }
    }

    /**
     * Find the index of an entity with a defined id.
     *
     * @param  string       $uuid The entity id to look for.
     * @return integer|null       The entity's index number in the collection or `null` if not found.
     */
    public function indexOfUuid($uuid)
    {
        foreach ($this as $key => $document) {
            if (!$document instanceof Document) {
                throw new Exception('Error, `indexOfUuid()` is only available on documents.');
            }
            if ($uuid === $document->uuid()) {
                return $key;
            }
        }
    }

    /**
     * Filters a copy of the items in the collection.
     *
     * @param  Closure $closure The closure to use for filtering, or an array of key/value pairs to match.
     * @return object           Returns a collection of the filtered items.
     */
    public function filter($closure)
    {
        $data = array_filter($this->_data, $closure);
        return new static(compact('data'));
    }

    /**
     * Applies a closure to all items in the collection.
     *
     * @param  Closure $closure The closure to apply.
     * @return object           This collection instance.
     */
    public function each($closure)
    {
        foreach ($this->_data as $key => $val) {
            $this->_data[$key] = $closure($val, $key, $this);
        }
        return $this;
    }

    /**
     * Applies a closure to a copy of all data in the collection
     * and returns the result.
     *
     * @param  Closure $closure The closure to apply.
     * @return mixed            Returns the set of filtered values inside a `Collection`.
     */
    public function map($closure)
    {
        $data = array_map($closure, $this->_data);
        return new static(compact('data'));
    }

    /**
     * Reduces, or folds, a collection down to a single value
     *
     * @param  Closure $closure The filter to apply.
     * @param  mixed   $initial Initial value.
     * @return mixed            The reduced value.
     */
    public function reduce($closure, $initial = false)
    {
        return array_reduce($this->_data, $closure, $initial);
    }

    /**
     * Extracts a slice of $length items starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param  integer $offset       The offset value.
     * @param  integer $length       The number of element to extract.
     * @param  boolean $preserveKeys Boolean indicating if keys must be preserved.
     * @return object                Returns a collection instance.
     */
    public function slice($offset, $length = null, $preserveKeys = true)
    {
        $data = array_slice($this->_data, $offset, $length, $preserveKeys);
        return new static(compact('data'));
    }

    /**
     * Sorts the objects in the collection.
     *
     * @param closure  $closure A compare function like strcmp or a custom closure. The
     *                          comparison function must return an integer less than, equal to, or
     *                          greater than zero if the first argument is considered to be respectively
     *                          less than, equal to, or greater than the second.
     * @param string   $sorter  The type of sorting, can be any sort function like `asort`,
     *                          'uksort', or `natsort`.
     * @return object           Returns the new sorted collection.
     */
    public function sort($closure = null, $sorter = null)
    {
        if (!$sorter) {
            $sorter = $closure === null ? 'sort' : 'usort';
        }
        if (!is_callable($sorter)) {
            throw new InvalidArgumentException("The passed parameter is not a valid sort function.");
        }
        $data = $this->_data;
        $closure === null ? $sorter($data) : $sorter($data, $closure);
        return new static(compact('data'));
    }

    /**
     * Eager loads relations.
     *
     * @param array $relations The relations to eager load.
     */
    public function embed($relations)
    {
        $this->schema()->embed($this, $relations);
    }

    /**
     * Converts the current state of the data structure to an array.
     *
     * @param  array $options The options array.
     * @return array          Returns the array value of the data in this `Collection`.
     */
    public function data($options = [])
    {
        return $this->to('array', $options);
    }

    /**
     * Creates and/or updates a collection and its direct relationship data in the datasource.
     *
     *
     * @param  array    $options Options:
     *                           - `'validate'`  _boolean_: If `false`, validation will be skipped, and the record will
     *                                                      be immediately saved. Defaults to `true`.
     * @return boolean           Returns `true` on a successful save operation, `false` otherwise.
     */
    public function broadcast($options = [])
    {
        $defaults = ['validate' => true];
        $options += $defaults;
        if ($options['validate'] && !$this->validates($options)) {
            return false;
        }
        $schema = $this->schema();
        return $schema->broadcast($this, $options);
    }

    /**
     * Similar to `->broadcast()` except the relationships has not been saved by default.
     *
     * @param  array   $options Same options as `->save()`.
     * @return boolean          Returns `true` on a successful save operation, `false` on failure.
     */
    public function save($options = [])
    {
        return $this->broadcast($options + ['embed' => false]);
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @return boolean Success.
     */
    public function delete()
    {
        $schema = $this->schema();
        return $schema->delete($this);
    }

    /**
     * Validates a collection.
     *
     * @param  array   $options Validates option.
     * @return boolean
     */
    public function validates($options = [])
    {
        $success = true;
        foreach ($this as $entity) {
            if (!$entity->validates($options)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Returns the errors from the last validate call.
     *
     * @return array The occured errors.
     */
    public function errors($options = [])
    {
        $errors = [];
        $errored = false;
        foreach ($this as $entity) {
            $result = $entity->errors();
            $errors[] = $result;
            if ($result) {
                $errored = true;
            }
        }
        return $errored ? $errors : [];
    }

    /**
     * Returns an array of all external relations and nested relations names.
     *
     * @param  boolean     $embedded Include or not embedded relations.
     * @return array|false           Returns an array of relation names or `false` when a circular loop is reached.
     */
    public function hierarchy($prefix = '', &$ignore = [])
    {
        $hash = spl_object_hash($this);
        if (isset($ignore[$hash])) {
            return false;
        }
        $ignore[$hash] = true;

        $result = [];

        foreach ($this as $entity) {
            if ($hierarchy = $entity->hierarchy($prefix, $ignore)) {
                $result += array_fill_keys($hierarchy, true);
            }
        }

        return array_keys($result);
    }

    /**
     * Exports a `Collection` object to another format.
     *
     * The supported values of `format` depend on the registered handlers.
     *
     * Once the appropriate handlers are registered, a `Collection` instance can be converted into
     * any handler-supported format, i.e.:
     *
     * ```php
     * $collection->to('json'); // returns a JSON string
     * $collection->to('xml'); // returns an XML string
     * ```
     *
     * @param  string $format  By default the only supported value is `'array'`. However, additional
     *                         format handlers can be registered using the `formats()` method.
     * @param  array  $options Options for converting the collection.
     * @return mixed           The converted collection.
     */
    public function to($format, $options = [])
    {
        $defaults = [
        'cast' => true
        ];

        $options += $defaults;

        $data = $options['cast'] ? Collection::toArray($this, $options) : $this;

        if (is_callable($format)) {
            return $format($data, $options);
        } elseif ($formatter = static::formats($format)) {
            return $formatter($data, $options);
        }
        return $data;
    }

    /**
     * Accessor method for adding format handlers to `Collection` instances.
     *
     * The values assigned are used by `Collection::to()` to convert `Collection` instances into
     * different formats, i.e. JSON.
     *
     * This can be accomplished in two ways. First, format handlers may be registered on a
     * case-by-case basis, as in the following:
     *
     * ```php
     * Collection::formats('json', function($collection, $options) {
     *  return json_encode($collection->to('array'));
     * });
     *
     * // You can also implement the above as a static class method, and register it as follows:
     * Collection::formats('json', 'my\custom\Formatter::toJson');
     * ```
     *
     * @param  string $format  A string representing the name of the format that a `Collection`
     *                         can be converted to. If `false`, reset the `$_formats` attribute.
     *                         If `null` return the content of the `$_formats` attribute.
     * @param  mixed  $handler The function that handles the conversion, either an anonymous function,
     *                         a fully namespaced class method or `false` to remove the `$format` handler.
     * @return mixed
     */
    public static function formats($format = null, $handler = null)
    {
        if (func_num_args() === 0) {
            return static::$_formats;
        }
        if (func_num_args() === 1) {
            return isset(static::$_formats[$format]) ? static::$_formats[$format] : null;
        }
        if ($format === false) {
            return static::$_formats = [];
        }
        if ($handler === false) {
            unset(static::$_formats[$format]);
            return;
        }
        return static::$_formats[$format] = $handler;
    }

    /**
     * Exports a `Collection` instance to an array. Used by `Collection::to()`.
     *
     * @param  mixed $data    Either a `Collection` instance, or an array representing a
     *                        `Collection`'s internal state.
     * @param  array $options Options used when converting `$data` to an array:
     *                        - `'handlers'` _array_: An array where the keys are fully-namespaced class
     *                          names, and the values are closures that take an instance of the class as a
     *                          parameter, and return an array or scalar value that the instance represents.
     *
     * @return array          Returns the value of `$data` as a pure PHP array, recursively converting all
     *                        sub-objects and other values to their closest array or scalar equivalents.
     */
    public static function toArray($data, $options = [])
    {
        $defaults = [
            'handlers' => []
        ];

        $options += $defaults;
        $result = [];

        foreach ($data as $key => $item) {
            switch (true) {
                case is_array($item):
                    $result[] = static::toArray($item, $options);
                break;
                case (!is_object($item)):
                    $result[] = $item;
                break;
                case (isset($options['handlers'][$class = get_class($item)])):
                    $result[] = $options['handlers'][$class]($item);
                break;
                case $item instanceof Document:
                    $result[] = $item->to('array', $options);
                break;
                case $item instanceof Traversable:
                    $result[] = static::toArray($item, $options);
                break;
                case (method_exists($item, '__toString')):
                    $result[] = (string) $item;
                break;
                default:
                    $result[] = $item;
                break;
            }
        }
        return $result;
    }
}
