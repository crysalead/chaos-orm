<?php
namespace Chaos\ORM\Collection;

use Chaos\ORM\Contrat\DataStoreInterface;
use Chaos\ORM\Contrat\HasParentsInterface;

use Chaos\ORM\ORMException;
use Chaos\ORM\Document;
use Chaos\ORM\Model;
use Chaos\ORM\Collection\Collection;

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
            'schema'  => null,
            'through' => null,
            'using'   => null,
            'exists'  => false
        ];
        $config += $defaults;
        $this->_parent = $config['parent'];
        $this->_schema = $config['schema'];
        $this->_through = $config['through'];
        $this->_using = $config['using'];

        foreach (['parent', 'schema', 'through', 'using'] as $name) {
            if (!$config[$name]) {
                throw new ORMException("Invalid through collection, `'{$name}'` is empty.");
            }
            $key = '_' . $name;
            $this->{$key} = $config[$name];
        }

        // Existing tags will require valid pivot ID, so existing data must be setted through the pivot table.
        if (!empty($config['exists'])) {
            return;
        }

        if ($this->_parent->has($this->_through)) {
            if (isset($config['data'])) {
                $this->_merge($config['data'], $config['exists']);
            }
            return;
        }

        $this->_parent->{$this->_through} = [];

        $config['data'] = isset($config['data']) ? $config['data'] : [];

        $this->amend($config['data'], ['exists' => $config['exists']]);
    }

    /**
     * Merge pivot data based on entities ids
     *
     * @param array   $data   The pivot data.
     * @param boolean $exists The existance value.
     */
    protected function _merge($data, $exists)
    {
        if (!$data) {
            $this->_parent->get($this->_through)->clear();
            return;
        }

        $pivot = $this->_parent->{$this->_through};

        $relThrough = $this->_parent->schema()->relation($this->_through);
        $through = $relThrough->to();
        $schema = $through::definition();
        $rel = $schema->relation($this->_using);
        $fromKey = $rel->keys('from');
        $toKey = $rel->keys('to');

        $i = 0;

        while ($i < $pivot->count()) {
            $found = false;
            $entity = $pivot->get($i);
            $id1 = $entity->get($fromKey);
            if ($id1 === null) {
                $pivot->splice($i, 1);
                continue;
            }
            foreach ($data as $key => $item) {
                $isDocument = $item instanceof Document;
                $id2 = $isDocument ? $item->get($toKey) : (isset($item[$toKey]) ?$item[$toKey] : null);

                if ((string) $id1 === (string) $id2) {
                    if ($isDocument) {
                        $entity->set($this->_using, $item);
                    } else {
                        $entity->get($this->_using)->amend($item);
                    }
                    unset($data[$key]);
                    $i++;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $pivot->splice($i, 1);
            }
        }

        foreach ($data as $entity) {
            $this[] = $entity;
        }
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
     * @param  object $parent The parent instance to unset.
     * @return self
     */
    public function unsetParent($parent)
    {
        $this->_parent->{$this->_through}->unsetParent($parent);
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
        $this->_parent->{$this->_through}->disconnect();
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
        if (!func_num_args()) {
            $data = [];
            foreach ($this as $value) {
                $data[] = $value;
            }
            return $data;
        }
        if ($entity = $this->_parent->{$this->_through}->get($offset)) {
            return $entity->{$this->_using};
        }
        return;
    }

    /**
     * Sets data to a specified offset and wraps all data array in its appropriate object type.
     *
     * @param  mixed   $offset  The offset. If offset is `null` data is simply appended to the set.
     * @param  mixed   $data    An array or an entity instance to set.
     * @return self             Return `this`.
     */
    public function set($offset = null, $data = [])
    {
        $name = $this->_through;
        $this->_parent->{$name}->set($offset, $this->_item($data));
        return $this;
    }

    /**
     * Sets data to a specified offset and wraps all data array in its appropriate object type.
     *
     * @param  mixed $offset  The offset. If offset is `null` data is simply appended to the set.
     * @param  mixed $data    An array or an entity instance to set.
     * @param  array $options Method options:
     *                        - `'exists'` _boolean_: Determines whether or not this entity exists
     * @return self           Return `this`.
     */
    public function setAt($offset = null, $data = [], $options = [])
    {
        $name = $this->_through;
        $this->_parent->{$name}->set($offset, $this->_item($data, $options), $options);
        return $this;
    }

    /**
     * Adds data into the `Collection` instance.
     *
     * @param  mixed   $data    An array or an entity instance to set.
     * @return self             Return `this`.
     */
    public function push($data = [])
    {
        $name = $this->_through;
        $this->_parent->{$name}->push($offset, $this->_item($data));
        return $this;
    }

    /**
     * Create a pivot instance.
     *
     * @param  mixed $data    The data.
     * @param  array $options Method options:
     *                        - `'exists'` _boolean_: Determines whether or not this entity exists
     * @return mixed          The pivot instance.
     */
    protected function _item($data, $options = [])
    {
        $name = $this->_through;
        $parent = $this->_parent;
        $relThrough = $parent->schema()->relation($name);
        $through = $relThrough->to();
        $id = $this->_parent->id();
        $item = $through::create($id !== null ? $relThrough->match($this->_parent) : [], $options);
        $item->setAt($this->_using, $data, $options);
        return $item;
    }

    /**
     * Returns a boolean indicating whether an offset exists for the
     * current `Collection`.
     *
     * @param  string $offset String or integer indicating the offset or
     *                        index of an entity in the set.
     * @return boolean        Result.
     */
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $data)
    {
        $this->set($offset, $data);
    }

    /**
     * Unset an offset.
     *
     * @param integer $offset The offset to unset.
     */
    #[\ReturnTypeWillChange]
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
    public function unset($name)
    {
        return $this->offsetUnset($name);
    }

    /**
     * Amend the collection modifications.
     *
     * @return self
     */
    public function amend($data = null, $options = []) {
        $name = $this->_through;
        if ($data !== null) {
            foreach ($data as $value) {
                $item = $this->_item($value, $options);
                $this->_parent->get($name)->setAt(null, $item, $options);
                $item->amend();
            }
        }
        $this->_parent->get($name)->amend();
        return $this;
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
        $this->_parent->{$this->_through}->clear();
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
    #[\ReturnTypeWillChange]
    public function key($full = false)
    {
        return $this->_parent->{$this->_through}->key();
    }

    /**
     * Returns the currently pointed to record in the set.
     *
     * @return object `Record`
     */
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
    public function valid()
    {
        return $this->_parent->{$this->_through}->valid();
    }

    /**
     * Counts the items of the object.
     *
     * @return integer Returns the number of items in the collection.
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->_parent->{$this->_through}->count();
    }

    /**
     * Return the collection indexed by an arbitrary field name.
     *
     * @param  string  $field   The field name to use for indexing
     * @param  boolean $byIndex If `true` return index numbers attached to the index instead of documents.
     * @return object           The indexed collection
     */
    public function indexBy($field, $byIndex = false)
    {
        $indexes = [];
        $collection = $this->_parent->{$this->_through};

        foreach ($this as $key => $document) {
            if (!($document instanceof Document)) {
                throw new ORMException("Only document can be indexed.");
            }

            $index = $document[$field];
            $indexes[$index][] = $byIndex ? $key : $document;
        }
        return $indexes;
    }

    /**
     * Find the index of an item (not optimized for negative fromIndex).
     *
     * @param  mixed   $item      The item to look for.
     * @param  integer $fromIndex The index to start the search at If the provided index value is a negative number,
     *                            it is taken as the offset from the end of the array.
     *                            Note: if the provided index is negative, the array is still searched from front to back
     * @return integer            The first index of the element in the array; -1 if not found.
     */
    public function indexOf($item, $fromIndex = 0)
    {
        $index = max($fromIndex >= 0 ? $fromIndex : $this->count() + $fromIndex, 0);
        $collection = $this->_parent->{$this->_through};
        $cpt = 0;

        foreach ($collection as $key => $entity) {
            $cpt++;
            if ($cpt < $index + 1) {
                continue;
            }
            if ($entity[$this->_using] === $item) {
                return $key;
            }
            $index++;
        }
        return -1;
    }

    /**
     * Find the last index of an item (not optimized for negative fromIndex).
     *
     * @param  mixed   $item      The item to look for.
     * @param  integer $fromIndex The index to start the search at If the provided index value is a negative number,
     *                            it is taken as the offset from the end of the array.
     *                            Note: if the provided index is negative, the array is still searched from front to back
     * @return integer            The first index of the element in the array; -1 if not found.
     */
    public function lastIndexOf($item, $fromIndex = 0)
    {
        $index = max($fromIndex >= 0 ? $fromIndex : $this->count() + $fromIndex, 0);
        $collection = $this->_parent->{$this->_through};
        $cpt = 0;
        $result = -1;

        foreach ($collection as $key => $entity) {
            $cpt++;
            if ($cpt < $index + 1) {
                continue;
            }
            if ($entity[$this->_using] === $item) {
                $result = $key;
            }
            $index++;
        }
        return $result;
    }

    /**
     * Find the index of an entity with a defined id.
     *
     * @param  mixed        $id The entity id to look for.
     * @return integer|null     The entity's index number in the collection or `-1` if not found.
     */
    public function indexOfId($id)
    {
        $collection = $this->_parent->{$this->_through};

        foreach ($collection as $key => $entity) {
            if (!($entity instanceof Model)) {
                throw new ORMException('Error, `indexOfId()` is only available on models.');
            }
            if ($entity[$this->_using]->id() === $id) {
                return $key;
            }
        }
        return -1;
    }

    /**
     * Filters a copy of the items in the collection.
     *
     * @param  Closure $closure The closure to use for filtering, or an array of key/value pairs to match.
     * @return object           Returns a collection of the filtered items.
     */
    public function filter($closure)
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
    public function apply($closure)
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
     * @param  integer $start        The start offset.
     * @param  integer $end          The end offset.
     * @return object                Returns a collection instance.
     */
    public function slice($start, $end = null)
    {
        $result = $this->_parent->{$this->_through}->slice($start, $end);

        $data = [];
        foreach ($result as $key => $value) {
            $data[$key] = $value->{$this->_using};
        }
        return new Collection(compact('data'));
    }

   /**
    * Changes the contents of an array by removing existing elements and/or adding new elements.
    *
    * @param  integer  offset The offset value.
    * @param  integer  length The number of element to extract.
    * @return Array           An array containing the deleted elements.
    */
    public function splice($offset, $length = 0)
    {
        $result = $this->_parent->{$this->_through}->splice($offset, $length);

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
     * Validates the collection.
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
        return Collection::format($format, $this, $options);
    }
}
