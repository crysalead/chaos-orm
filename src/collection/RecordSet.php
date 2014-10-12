<?php
namespace chaos\collection;

/**
 * `RecordSet` provide context-specific features for working with sets of data persisted by a backend data store.
 */
class RecordSet extends \collection\Collection
{
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
    protected $_pathKey = null;

    /**
     * The fully-namespaced class name of the model object to which this entity set is bound. This
     * is usually the model that executed the query which created this object.
     *
     * @var string
     */
    protected $_model = null;

    /**
     * @var object
     */
    protected $_query = null;

    /**
     * An iterable instance.
     *
     * @var object
     */
    protected $_cursor = null;

    /**
     * Indicates whether the current position is valid or not.
     *
     * @var boolean
     */
    protected $_valid = true;

    /**
     * Contains an array of backend-specific meta datas (like pagination datas)
     *
     * @var array
     */
    protected $_meta = [];

    /**
     * Setted to `true` when the collection has begun iterating.
     *
     * @var integer
     */
    protected $_started = false;

    /**
     * Indicates whether this array was part of a document loaded from a data source, or is part of
     * a new document, or is in newly-added field of an existing document.
     *
     * @var boolean
     */
    protected $_exists = false;

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
            'exists'   => false,
            'parent'   => null,
            'rootKey'  => null,
            'model'    => null,
            'meta'     => null,
            'cursor'   => null
        ];
        $config += $defaults;
        $this->_exists = $config['exists'];
        $this->_parent = $config['parent'];
        $this->_rootPath = $config['rootPath'];
        $this->_model = $config['model'];
        $this->_meta = $config['meta'];
        $this->_cursor = $config['cursor'];
    }

    /**
     * A flag indicating whether or not the items of this collection exists.
     *
     * @return boolean `True` if exists, `false` otherwise.
     */
    public function exists()
    {
        return $this->_exists;
    }

    /**
     * Returns the object's parent `Document` object.
     *
     * @return object
     */
    public function parent()
    {
        return $this->_parent;
    }

    /**
     * Returns the model which this particular collection is based off of.
     *
     * @return string The fully qualified model class name.
     */
    public function model()
    {
        return $this->_model;
    }

    /**
     * Gets the meta datas.
     *
     * @return array .
     */
    public function meta()
    {
        return $this->_meta;
    }

    /**
     * Return's the cursor instance.
     *
     * @return object
     */
    public function cursor()
    {
        return $this->_cursor;
    }

    /**
     * Returns a boolean indicating whether an offset exists for the
     * current `Collection`.
     *
     * @param string $offset String or integer indicating the offset or
     *        index of an entity in the set.
     * @return boolean Result.
     */
    public function offsetExists($offset)
    {
        $this->offsetGet($offset);
        return array_key_exists($offset, $this->_data);
    }

    /**
     * Gets an `Entity` object using PHP's array syntax, i.e. `$documents[3]` or `$records[5]`.
     *
     * @param mixed $offset The offset.
     * @return mixed Returns an `Entity` object if exists otherwise returns `null`.
     */
    public function offsetGet($offset)
    {
        while (!array_key_exists($offset, $this->_data) && $this->_populate()) {}

        if (array_key_exists($offset, $this->_data)) {
            return $this->_data[$offset];
        }
        return null;
    }

    /**
     * Adds the specified object to the `Collection` instance, and assigns associated metadata to
     * the added object.
     *
     * @param string $offset The offset to assign the value to.
     * @param mixed $data The entity object to add.
     * @return mixed Returns the set `Entity` object.
     */
    public function offsetSet($offset, $data)
    {
        $this->offsetGet($offset);
        return $this->_set($data, $offset);
    }

    /**
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        $this->offsetGet($offset);
        prev($this->_data);
        if (key($this->_data) === null) {
            $this->rewind();
        }
        unset($this->_data[$offset]);
    }

    /**
     * Returns the item keys.
     *
     * @return array The keys of the items.
     */
    public function keys()
    {
        $this->offsetGet(null);
        return parent::keys();
    }

    /**
     * Returns the item values.
     *
     * @return array The keys of the items.
     */
    public function values()
    {
        $this->offsetGet(null);
        return parent::values();
    }

    /**
     * Gets the raw array value of the `Collection`.
     *
     * @return array Returns an "unboxed" array of the `Collection`'s value.
     */
    public function raw()
    {
        $this->offsetGet(null);
        return parent::raw();
    }

    /**
     * Returns the currently pointed to record's unique key.
     *
     * @param boolean $full If true, returns the complete key.
     * @return mixed
     */
    public function key($full = false)
    {
        if ($this->_started === false) {
            $this->current();
        }
        if ($this->_valid) {
            $key = key($this->_data);
            return (is_array($key) && !$full) ? reset($key) : $key;
        }
        return null;
    }

    /**
     * Returns the currently pointed to record in the set.
     *
     * @return object `Record`
     */
    public function current()
    {
        if (!$this->_started) {
            $this->rewind();
        }
        if (!$this->_valid) {
            return false;
        }
        return current($this->_data);
    }

    /**
     * Returns the next document in the set, and advances the object's internal pointer. If the end
     * of the set is reached, a new document will be fetched from the data source connection handle
     * If no more documents can be fetched, returns `null`.
     *
     * @return mixed Returns the next document in the set, or `false`, if no more documents are
     *         available.
     */
    public function next()
    {
        if (!$this->_started) {
            $this->rewind();
        }
        next($this->_data);
        $this->_valid = key($this->_data) !== null;

        if (!$this->_valid) {
            $this->_valid = $this->_populate() !== null;
        }
        return current($this->_data);
    }

    /**
     * Rewinds the collection to the beginning.
     */
    public function rewind()
    {
        $this->_started = true;
        reset($this->_data);
        $this->_valid = !empty($this->_data) || $this->_populate() !== null;
        return current($this->_data);
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        if (!$this->_started) {
            $this->rewind();
        }
        return $this->_valid;
    }

    /**
     * Overrides parent `find()` implementation to enable key/value-based filtering of entity
     * objects contained in this collection.
     *
     * @param mixed $filter Callback to use for filtering, or array of key/value pairs which entity
     *        properties will be matched against.
     * @param array $options Options to modify the behavior of this method. See the documentation
     *        for the `$options` parameter of `lithium\util\Collection::find()`.
     * @return mixed The filtered items. Will be an array unless `'collect'` is defined in the
     *         `$options` argument, then an instance of this class will be returned.
     */
    public function find($filter, $options = [])
    {
        $this->offsetGet(null);
        return parent::find($filter, $options);
    }

    /**
     * Applies a callback to all data in the collection.
     *
     * Overridden to load any data that has not yet been loaded.
     *
     * @param callback $filter The filter to apply.
     * @return object This collection instance.
     */
    public function each($filter)
    {
        $this->offsetGet(null);
        return parent::each($filter);
    }

    /**
     * Applies a callback to a copy of all data in the collection
     * and returns the result.
     *
     * @param callback $callback The callback to apply.
     *
     * @return mixed    Returns the set of filtered values inside a `Collection`.
     */
    public function map($filter, $options = [])
    {
        $this->offsetGet(null);
        $data = parent::map($filter, $options);
        return $data;
    }

    /**
     * Reduce, or fold, a collection down to a single value
     *
     * @param  callback $filter The filter to apply.
     * @param  mixed    $initial Initial value.
     *
     * @return mixed    A single reduced value.
     */
    public function reduce($filter, $initial = false)
    {
        $this->offsetGet(null);
        return parent::reduce($filter, $initial);
    }

    /**
     * Sorts the objects in the collection.
     *
     * @param  callback $callback A compare function like strcmp or a custom closure. The
     *                  comparison function must return an integer less than, equal to, or
     *                  greater than zero if the first argument is considered to be respectively
     *                  less than, equal to, or greater than the second.
     * @param  string   $sorter The type of sorting, can be any sort function like `asort`,
     *                  'uksort', or `natsort`.
     *
     * @return object   Return `$this`.
     */
    public function sort($field = 'id', $options = [])
    {
        $this->offsetGet(null);
        return parent::sort($sorter, $options);
    }

    /**
     * Allows several properties to be assigned at once.
     *
     * For example:
     * ```php
     * $collection->set(['title' => 'Lorem Ipsum', 'value' => 42]);
     * ```
     *
     * @param $values An associative array of fields and values to assign to the `Collection`.
     */
    public function set($values)
    {
        foreach ($values as $key => $val) {
            $this[$key] = $val;
        }
    }

    protected function _populate() {

    }

    /**
     * Converts the current state of the data structure to an array.
     *
     * @return array Returns the array value of the data in this `Collection`.
     */
    public function data()
    {
        return $this->toArray();
    }

    /**
     * Clean up
     */
    public function close()
    {
        if ($this->_cursor)) {
            $this->_cursor->close();
        }
    }

    /**
     * Ensures that the data set's connection is closed when the object is destroyed.
     */
    public function __destruct()
    {
        $this->close();
    }

}
