<?php
namespace Chaos\Collection;

use Chaos\Contrat\DataStoreInterface;
use Chaos\Contrat\HasParentsInterface;

use Chaos\ChaosException;
use Chaos\Collection\Collection;

/**
 * `Through` provide context-specific features for working with sets of data persisted by a backend data store.
 */
class Through implements DataStoreInterface, HasParentsInterface, \ArrayAccess, \Iterator, \Countable
{
    /**
     * A reference to this object's parent `Document` object.
     *
     * @var object
     */
    protected $_parent = null;

    /**
     * The schema to which this collection is bound. This
     * is usually the schema that executed the query which created this object.
     *
     * @var object
     */
    protected $_schema = null;

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
     * Creates an alias on an other collection.
     *
     * @param array $config Possible options are:
     *                      - `'parent'`    _object_ : The parent instance.
     *                      - `'schema'`    _object_ : The attached schema.
     *                      - `'through'`   _object_ : A collection instance.
     *                      - `'using'`     _string_ : The field name to extract from collection's entities.
     *                      - `'data'`      _array_  : Some data to set on the collection.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'parent'  => null,
            'schema'   => null,
            'through' => null,
            'using'   => null,
            'data'     => []
        ];
        $config += $defaults;
        $this->_parent = $config['parent'];
        $this->_schema = $config['schema'];
        $this->_through = $config['through'];
        $this->_using = $config['using'];

        foreach (['parent', 'schema', 'through', 'using'] as $name) {
            if (!$config[$name]) {
                throw new ChaosException("Invalid through collection, `'{$name}'` is empty.");
            }
            $key = '_' . $name;
            $this->{$key} = $config[$name];
        }

        foreach ($config['data'] as $entity) {
            $this[] = $entity;
        }
    }

    /**
     * Gets/sets the collector.
     *
     * @param  object $collector The collector instance to set or none to get it.
     * @return mixed          Returns the parent value on get or `$this` otherwise.
     */
    public function collector($collector = null)
    {
        if (!func_num_args()) {
            return $this->_parent->{$this->_through}->collector();
        }
        $this->_parent->{$this->_through}->collector($collector);
        return $this;
    }

    /**
     * Get parents.
     *
     * @return Map Returns the parents map.
     */
    public function parents()
    {
        return $this->_parent->{$this->_through}->parents();
    }

    /**
     * Set a parent.
     *
     * @param  pbject $parent The parent instance to set.
     * @param  string $from   The parent from field to set.
     * @return self
     */
    public function setParent($parent, $from)
    {
        $this->_parent->{$this->_through}->setParent($parent, $from);
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
        $this->_parent->{$this->_through}->removeParent($parent);
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
            return $this->_parent->{$this->_through}->exists();
        }
        $this->_parent->{$this->_through}->exists($parent);
        return $this;
    }

    /**
     * Gets/sets the schema instance.
     *
     * @param  Object schema The schema instance to set or none to get it.
     * @return Object        The schema instance or `$this` on set.
     */
    public function schema()
    {
        return $this->_schema;
    }

    /**
     * Gets the base basePath.
     *
     * @return string
     */
    public function basePath()
    {
        return '';
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
     * Gets an `Entity` object.
     *
     * @param  integer $offset The offset.
     * @return mixed          Returns an `Entity` object if exists otherwise returns `undefined`.
     */
    public function get($offset = null)
    {
        if ($entity = $this->_parent->{$this->_through}->get($offset)) {
            return $entity->{$this->_using};
        }
        return;
    }

    /**
     * Sets data to a specified offset and wraps all data array in its appropriate object type.
     *
     * @param  mixed  $data    An array or an entity instance to set.
     * @param  mixed  $offset  The offset. If offset is `null` data is simply appended to the set.
     * @param  array  $options Any additional options to pass to the `Entity`'s constructor.
     * @return object          Returns the inserted instance.
     */
    public function set($offset = null, $data = [])
    {
        $name = $this->_through;
        $parent = $this->_parent;
        $relThrough = $parent->schema()->relation($name);
        $through = $relThrough->to();

        $item = $through::create($this->_parent->exists() ? $relThrough->match($this->_parent) : []);
        $item->{$this->_using} = $data;

        return $this->_parent->{$name}->set($offset, $item);
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
     * Unsets an offset.
     *
     * @param integer $offset The offset to unset.
     */
    public function offsetUnset($offset)
    {
        unset($this->_parent->{$this->_through}[$offset]);
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
     * @param  Closure $closure The closure to use for filtering, or an array of key/value pairs to match.
     * @return object           Returns a collection of the filtered items.
     */
    public function find($closure)
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
     * @return mixed            Returns the set of filtered values inside a `Collection`.
     */
    public function map($closure)
    {
        $data = [];
        foreach ($this as $val) {
            $data[] = $closure($val);
        }
        return new Collection(compact('data'));
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
        $result = $initial;
        foreach ($this as $val) {
            $result = $closure($result, $val);
        }
        return $result;
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
        } elseif ($formatter = Collection::formats($format)) {
            return $formatter($data, $options);
        }
        return $data;
    }
}
