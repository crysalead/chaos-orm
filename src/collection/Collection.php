<?php
namespace chaos\collection;

use ArrayAccess;
use InvalidArgumentException;
use chaos\Model;

/**
 * `Collection` provide context-specific features for working with sets of data persisted by a backend data store.
 */
class Collection implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'collector' => 'chaos\Collector'
    ];

    /**
     * The collector instance.
     *
     * @var object
     */
    protected $_collector = null;

    /**
     * A reference to this object's parent `Document` object.
     *
     * @var object
     */
    protected $_parent = null;

    /**
     * If this `Collection` instance has a parent document (see `$_parent`), this value indicates
     * the key name of the parent document that contains it.
     *
     * @see lithium\data\Collection::$_parent
     * @var string
     */
    protected $_rootPath = null;

    /**
     * The fully-namespaced class name of the model object to which this entity set is bound. This
     * is usually the model that executed the query which created this object.
     *
     * @var string
     */
    protected $_model = null;

    /**
     * Loaded items on construct.
     *
     * @var array
     */
    protected $_loaded = [];

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
     *                      - `'rootPath'`  _string_ : A dotted string field path.
     *                      - `'model'`     _string_ : The attached model class name.
     *                      - `'meta'`      _array_  : Some meta data.
     *                      - `'data'`      _array_  : The collection data.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'collector' => null,
            'parent'    => null,
            'rootPath'  => null,
            'model'     => null,
            'meta'      => [],
            'data'      => [],
            'exists'    => false
        ];
        $config += $defaults;

        $this->_collector = $config['collector'];
        $this->_parent = $config['parent'];
        $this->_rootPath = $config['rootPath'];
        $this->_model = $config['model'];
        $this->_meta = $config['meta'];

        $this->_loaded = $config['data'];
        foreach ($this->_loaded as $key => $value) {
            $this->_set($value, $key, ['exists' => $config['exists']]);
        }
        $this->_loaded = $this->_data;
    }

    /**
     * Gets/sets the collector.
     *
     * @param  object $collector The collector instance to set or none to get the current one.
     * @return object            A collector instance.
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
     * Gets/sets the parent instance.
     *
     * @param  object $parent The parent instance to set or none to get the current one.
     * @return object
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
     * Gets the rootPath.
     *
     * @return string
     */
    public function rootPath()
    {
        return $this->_rootPath;
    }

    /**
     * Returns the model on which this particular collection is based.
     *
     * @return string The fully qualified model class name.
     */
    public function model()
    {
        return $this->_model;
    }

    /**
     * Gets the meta data.
     *
     * @return array .
     */
    public function meta()
    {
        return $this->_meta;
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
     * Returns a boolean indicating whether an offset exists for the current `Collection`.
     *
     * @param  string $offset String or integer indicating the offset or index of an entity in the set.
     * @return boolean        Result.
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Gets an `Entity` object using PHP's array syntax, i.e. `$documents[3]` or `$records[5]`.
     *
     * @param  mixed $offset The offset.
     * @return mixed         Returns an `Entity` object if exists otherwise returns `null`.
     */
    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->_data)) {
            return $this->_data[$offset];
        }
        return;
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
        return $this->_set($data, $offset);
    }

    /**
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        $this->_skipNext = $offset === key($this->_data);
        unset($this->_data[$offset]);
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
     * Filters a copy of the items in the collection.
     *
     * @param  Closure $closure The closure to use for filtering, or an array of key/value pairs to match.
     * @return object           Returns a collection of the filtered items.
     */
    public function find($closure)
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
    public function map($closure, $options = [])
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
     * Sets data to a specified offset and wraps all data array in its appropriate object type.
     *
     * @param  mixed  $data    An array or an entity instance to set.
     * @param  mixed  $offset  The offset. If offset is `null` data is simply appended to the set.
     * @param  array  $options Any additional options to pass to the `Entity`'s constructor.
     * @return object          Returns the inserted instance.
     */
    protected function _set($data = null, $offset = null, $options = [])
    {
        $defaults = ['defaults' => false];
        $rootPath = $this->_rootPath;
        $model = $this->_model;
        $parent = $this;
        $collector = $this->collector();
        $options = compact('collector', 'model', 'rootPath', 'parent') + $options + $defaults;

        if ($model) {
            $data = $model::schema()->cast(null, $data, $options);
        }

        return $offset !== null ? $this->_data[$offset] = $data : $this->_data[] = $data;
    }

    /**
     * Eager loads relations.
     *
     * @param array $relations The relations to eager load.
     */
    public function embed($relations)
    {
        $model = $this->_model;
        $model::schema()->embed($this, $relations);
    }

    /**
     * Gets the plain array value of the `Collection`.
     *
     * @return array Returns an "unboxed" array of the `Collection`'s value.
     */
    public function get()
    {
        return $this->_data;
    }

    /**
     * Converts the current state of the data structure to an array.
     *
     * @param  array $options The options array.
     * @return array          Returns the array value of the data in this `Collection`.
     */
    public function data($options = [])
    {
        return array_values(static::toArray($this, $options));
    }

    /**
     * Validates a collection.
     *
     * @param  array   $options Validates option.
     * @return boolean
     */
    public function validate($options = [])
    {
        $success = true;
        foreach ($this as $entity) {
            if (!$entity->validate($options)) {
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
        foreach ($this as $entity) {
            $errors[] = $entity->errors();
        }
        return $errors;
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
                    $result[$key] = static::toArray($item, $options);
                break;
                case (!is_object($item)):
                    $result[$key] = $item;
                break;
                case (isset($options['handlers'][$class = get_class($item)])):
                    $result[$key] = $options['handlers'][$class]($item);
                break;
                case $item instanceof Model:
                    $result[$key] = $item->to('array', $options);
                break;
                case $item instanceof ArrayAccess:
                    $result[$key] = static::toArray($item, $options);
                break;
                case (method_exists($item, '__toString')):
                    $result[$key] = (string) $item;
                break;
                default:
                    $result[$key] = $item;
                break;
            }
        }
        return $result;
    }
}
