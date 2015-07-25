<?php
namespace chaos\model\collection;

use chaos\SourceException;
use chaos\model\collection\Collection;

/**
 * `Through` provide context-specific features for working with sets of data persisted by a backend data store.
 */
class Through implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * A reference to this object's parent `Document` object.
     *
     * @var object
     */
    protected $_parent = null;

    /**
     * The fully-namespaced class name of the model object to which this entity set is bound. This
     * is usually the model that executed the query which created this object.
     *
     * @var string
     */
    protected $_model = null;

    /**
     * A reference to this object's parent `Document` object.
     *
     * @var object
     */
    protected $_through = null;

    /**
     * A reference to this object's parent `Document` object.
     *
     * @var object
     */
    protected $_using = null;

    public function __construct($config = [])
    {
        $defaults = [
            'parent'  => null,
            'model'   => null,
            'through' => null,
            'using'   => null,
            'data'     => []
        ];
        $config += $defaults;
        $this->_parent = $config['parent'];
        $this->_model = $config['model'];
        $this->_through = $config['through'];
        $this->_using = $config['using'];

        foreach (['parent', 'model', 'through', 'using'] as $name) {
            if (!$config[$name]) {
                throw new SourceException("Invalid through collection, `'{$name}'` is empty.");
            }
            $key = '_' . $name;
            $this->{$key} = $config[$name];
        }

        foreach ($config['data'] as $entity) {
            $this[] = $entity;
        }
    }

    /**
     * Gets/sets the parent.
     *
     * @param  object $parent The parent instance to set or `null` to get the current one.
     * @return object
     */
    public function parent($parent = null)
    {
        if (!func_num_args()) {
            return $this->_parent->{$this->_through}->parent();
        }
        $this->_parent->{$this->_through}->parent($parent);
        return $this;
    }

    /**
     * Get the base rootPath.
     *
     * @return string
     */
    public function rootPath()
    {
        return '';
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
        return $this->_parent->{$this->_through}->meta();
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

        return new Collection(compact('data'));
    }

    /**
     * Returns a boolean indicating whether an offset exists for the
     * current `Collection`.
     *
     * @param  string $offset String or integer indicating the offset or
     *                        index of an entity in the set.
     * @return boolean        Result.
     */
    public function offsetExists($offset)
    {
        return $this->_parent->{$this->_through}->offsetExists($offset);
    }

    /**
     * Gets an `Entity` object using PHP's array syntax, i.e. `$documents[3]` or `$records[5]`.
     *
     * @param  mixed $offset The offset.
     * @return mixed         Returns an `Entity` object if exists otherwise returns `null`.
     */
    public function offsetGet($offset)
    {
        if ($entity = $this->_parent->{$this->_through}[$offset]) {
            return $entity->{$this->_using};
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
        $name = $this->_through;
        $parent = $this->parent();
        $relThrough = $parent::relation($name);
        $through = $relThrough->to();

        $item = $through::create($this->_parent->exists() ? $relThrough->match($this->_parent) : []);
        $item->{$this->_using} = $data;

        return $offset !== null ? $this->_parent->{$name}[$offset] = $item : $this->_parent->{$name}[] = $item;
    }

    /**
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        unset($this->_parent->{$this->_through}[$offset]);
    }

    /**
     * Merge another collection to this collection.
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
        return $this->_parent->{$this->_through}->keys();
    }

    /**
     * Returns the currently pointed to record's unique key.
     *
     * @param  boolean $full If true, returns the complete key.
     * @return mixed
     */
    public function key($full = false)
    {
        return $this->_parent->{$this->_through}->key();
    }

    /**
     * Returns the currently pointed to record in the set.
     *
     * @return object `Record`
     */
    public function current()
    {
        $entity = $this->_parent->{$this->_through}->current();
        if ($entity) {
            return $entity->{$this->_using};
        }
    }

    /**
     * Moves backward to the previous item.  If already at the first item,
     * moves to the last one.
     *
     * @return mixed The current item after moving or the last item on failure.
     */
    public function prev()
    {
        $entity = $this->_parent->{$this->_through}->prev();
        if ($entity) {
            return $entity->{$this->_using};
        }
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
        $entity = $this->_parent->{$this->_through}->next();
        if ($entity) {
            return $entity->{$this->_using};
        }
    }

    /**
     * Rewinds the collection to the beginning.
     */
    public function rewind()
    {
        $this->_parent->{$this->_through}->rewind();
        return $this->current();
    }

    /**
     * Moves forward to the last item.
     *
     * @return mixed The current item after moving.
     */
    public function end()
    {
        $this->_parent->{$this->_through}->end();
        return $this->current();
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        return $this->_parent->{$this->_through}->valid();
    }

    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    public function count()
    {
        return $this->_parent->{$this->_through}->count();
    }

    /**
     * Filters a copy of the items in the collection.
     *
     * @param  mixed $closure The closure to use for filtering, or an array of key/value pairs to match.
     *
     * @return mixed          Returns a collection of the filtered items.
     */
    public function find($closure, $options = [])
    {
        $data = [];
        foreach ($this as $val) {
            if ($closure($val)) {
                $data[] = $val;
            }
        }
        return new Collection(compact('data'));
    }

    /**
     * Applies a closure to all items in the collection.
     *
     * @param  Closure $closure The closure to apply.
     *
     * @return object           This collection instance.
     */
    public function each($closure)
    {
        foreach ($this as $key => $val) {
            $this->offsetSet($key, $closure($val, $key, $this));
        }
        return $this;
    }

    /**
     * Applies a closure to a copy of all data in the collection
     * and returns the result.
     *
     * @param  Closure $closure The closure to apply.
     *
     * @return mixed            Returns the set of filtered values inside a `Collection`.
     */
    public function map($closure, $options = [])
    {
        $data = [];
        foreach ($this as $val) {
            $data[] = $closure($val);
        }
        return new Collection(compact('data'));
    }

    /**
     * Reduce, or fold, a collection down to a single value
     *
     * @param  closure $closure The filter to apply.
     * @param  mixed   $initial Initial value.
     *
     * @return mixed            A single reduced value.
     */
    public function reduce($closure, $initial = false)
    {
        $result = $initial;
        foreach ($this as $val) {
            $result = $closure($result, $val);
        }
        return $result;
    }

    /**
     * Extract a slice of $length items starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param  integer $offset The offset value.
     * @param  integer $length The number of element to extract
     *
     * @return array
     */
    public function slice($offset, $length = null, $preserveKeys = true)
    {
        $result = $this->_parent->{$this->_through}->slice($offset, $length, $preserveKeys);

        $data = [];
        foreach ($result as $key => $value) {
            $data[$key] = $value->{$this->_using};
        }
        return new Collection(compact('data'));
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
     * Converts the current state of the data structure to an array.
     *
     * @return array Returns the array value of the data in this `Collection`.
     */
    public function data($options = [])
    {
        return array_values(Collection::toArray($this, $options));
    }
}
