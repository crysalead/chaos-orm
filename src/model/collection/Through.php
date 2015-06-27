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

    /**
     * The items contained in the collection.
     *
     * @var array
     */
    protected $_data = null;

    /**
     * Setted to `true` when the collection has begun iterating.
     *
     * @var integer
     */
    protected $_started = false;

    /**
     * Workaround to allow consistent `unset()` in `foreach`.
     *
     * Note: the edge effet of this behavior is the following:
     * {{{
     *   $collection = new Collection(['data' => ['1', '2', '3']]);
     *   unset($collection[0]);
     *   $collection->next();   // returns 2 instead of 3 when no `skipNext`
     * }}}
     */
    protected $_skipNext = false;

    public function __construct($config = [])
    {
        $defaults = [
            'parent'  => null,
            'model'   => null,
            'through' => null,
            'using'   => null
        ];
        $config += $defaults;
        $this->_parent = $config['parent'];
        $this->_model = $config['model'];
        $this->_through = $config['through'];
        $this->_using = $config['using'];

        foreach (['parent', 'through', 'using'] as $name) {
            if (!$config[$name]) {
                throw new SourceException("Invalid through collection, `'{$name}'` is empty.");
            }
            $key = '_' . $name;
            $this->{$key} = $config[$name];
        }

        $through = $this->_through;
        $this->_data = $this->_parent->{$through};
    }

    /**
     * Gets/sets the parent.
     *
     * @param  object $parent The parent instance to set or `null` to get the current one.
     * @return object
     */
    public function parent($parent = null)
    {
        return $this->_data->parent($parent);
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
        return $this->_data->meta();
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
        return $this->_data->offsetExists($offset);
    }

    /**
     * Gets an `Entity` object using PHP's array syntax, i.e. `$documents[3]` or `$records[5]`.
     *
     * @param  mixed $offset The offset.
     * @return mixed         Returns an `Entity` object if exists otherwise returns `null`.
     */
    public function offsetGet($offset)
    {
        if ($entity = $this->_data[$offset]) {
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
        $through = $this->_data->model();

        $data = $through->match($this->_parent) + [
            $this->_using => $data
        ];

        return $offset !== null ? $this->_data[$offset] = $data : $this->_data[] = $data;
    }

    /**
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    /**
     * Returns the currently pointed to record's unique key.
     *
     * @param  boolean $full If true, returns the complete key.
     * @return mixed
     */
    public function key($full = false)
    {
        return $this->_data->key();
    }

    /**
     * Returns the currently pointed to record in the set.
     *
     * @return object `Record`
     */
    public function current()
    {
        $entity = $this->_data->current();
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
        $entity = $this->_data->prev();
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
        $entity = $this->_data->next();
        if ($entity) {
            return $entity->{$this->_using};
        }
    }

    /**
     * Alias to `::rewind()`.
     *
     * @return mixed The current item after rewinding.
     */
    public function first()
    {
        return $this->_data->rewind();
    }

    /**
     * Rewinds the collection to the beginning.
     */
    public function rewind()
    {
        return $this->_data->rewind();
    }

    /**
     * Moves forward to the last item.
     *
     * @return mixed The current item after moving.
     */
    public function end()
    {
        $this->_data->end();
        return $this->current();
    }

    /**
     * Checks if current position is valid.
     *
     * @return boolean `true` if valid, `false` otherwise.
     */
    public function valid()
    {
        return $this->_data->valid();
    }

    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    public function count()
    {
        return $this->_data->count();
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
    public function data()
    {
        return Collection::toArray($this);
    }
}
