<?php
namespace Chaos\ORM;

use Lead\Set\Set;

/**
 * The `Relationship` class encapsulates the data and functionality necessary to link two model together.
 */
class Relationship
{
    /**
     * A one-to-one or many-to-one relationship in which a key contains an ID value linking to
     * another document or record.
     */
    const LINK_KEY = 'key';

    /**
     * A many-to-many relationship in which a key contains an embedded array of IDs linking to other
     * records or documents.
     */
    const LINK_KEY_LIST = 'keylist';

    /**
     * A relationship linking type defined by one document or record (or multiple) being embedded
     * within another.
     */
    const LINK_EMBEDDED = 'embedded';

    /**
     * The reciprocal of `LINK_EMBEDDED`, this defines a linking type wherein an embedded document
     * references the document that contains it.
     */
    const LINK_CONTAINED = 'contained';

    /**
     * The relation/field name.
     *
     * @var string
     */
    protected $_name = null;

    /**
     * The counterpart relation.
     *
     * @var object|null
     */
    protected $_counterpart = null;

    /**
     * The type of relationship.
     *
     * @var string
     */
    protected $_type = '';

    /**
     * Mathing keys definition.
     *
     * @var array
     */
    protected $_keys = [];

    /**
     * The fully namespaced class name this relationship originates.
     *
     * @var string
     */
    protected $_from = null;

    /**
     * The fully namespaced class name this relationship targets.
     *
     * @var string
     */
    protected $_to = null;

    /**
     * The type of linking.
     *
     * @var string
     */
    protected $_link = null;

    /**
     * The field names to filter on.
     *
     * @var mixed
     */
    protected $_fields = true;

    /**
     * The conditions to filter on.
     *
     * @var array
     */
    protected $_conditions = [];

    /**
     * The embedded mode.
     *
     * @var boolean
     */
    protected $_embedded = false;

    /**
     * The naming conventions instance to use.
     *
     * @var object
     */
    protected $_conventions = null;

    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'name'`        _string_ : The field name used for accessing the related data.
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name could be `'comments'`.
     *                      - `'keys'`        _mixed_  : Mathing keys definition, where the key is the key in the originating model,
     *                                                   and the value is the key in the target model (i.e. `['fromId' => 'toId']`).
     *                      - `'from'`        _string_ : The fully namespaced class name this relationship originates.
     *                      - `'to'`          _string_ : The fully namespaced class name this relationship targets.
     *                      - `'link'`        _string_ : A constant specifying how the object bound to the originating
     *                                                   model is linked to the object bound to the target model. For relational
     *                                                   databases, the only valid value is `LINK_KEY`, which means a foreign
     *                                                   key in one object matches another key (usually the primary key) in the other.
     *                                                   For document-oriented and other non-relational databases, different types of
     *                                                   linking, including key lists or even embedding.
     *                      - `'fields'`      _mixed_  : An array of the subset of fields that should be selected
     *                                                   from the related object(s) by default. If set to `true` (the default), all
     *                                                   fields are selected.
     *                      - `'conditions'`  _mixed_  : A string or array containing additional conditions
     *                                                   on the relationship association. If a string, can contain a literal SQL fragment or
     *                                                   other database-native value. If an array, maps fields from the related object
     *                                                   either to fields elsewhere, or to arbitrary expressions. In either case, _the
     *                                                   values specified here will be literally interpreted by the database_.
     *                      - `'conventions'` _object_ : The naming conventions instance to use.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'        => null,
            'counterpart' => null,
            'keys'        => null,
            'from'        => null,
            'to'          => null,
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'conditions'  => [],
            'embedded'    => false,
            'conventions' => null
        ];

        $config += $defaults;

        foreach (['from', 'to'] as $value) {
            if (!$config[$value]) {
                throw new ORMException("The relationship `'{$value}'` option can't be empty.");
            }
        }

        $this->_conventions = $config['conventions'] ?: new Conventions();

        if (!$config['keys']) {
            $key = $this->_conventions->apply('key');
            $config['keys'] = [$key => $this->_conventions->apply('reference', $config['from'])];
        }

        if (!$config['name']) {
            $config['name'] = $this->_conventions->apply('field', $config['to']);
        }

        $this->_name = $config['name'];
        $this->_counterpart = $config['counterpart'];
        $this->_from = $config['from'];
        $this->_to = $config['to'];
        $this->_keys = $config['keys'];
        $this->_link = $config['link'];
        $this->_fields = $config['fields'];
        $this->_conditions = $config['conditions'];
        $this->_embedded = $config['embedded'];

        $pos = strrpos(static::class, '\\');
        $this->_type = lcfirst(substr(static::class, $pos !== false ? $pos + 1 : 0));
    }

    /**
     * Allows relationship configuration items to be queried by name as methods.
     *
     * @param  string $name The name of the configuration item to query.
     * @param  array  $args Unused.
     * @return mixed        Returns the value of the given configuration item.
     */
    public function __call($name, $args = [])
    {
        $attribute = "_{$name}";
        return isset($this->{$attribute}) ? $this->{$attribute} : null;
    }

    /**
     * Returns the counterpart relation.
     *
     * @return object
     */
    public function counterpart()
    {
        if ($this->_counterpart) {
            return $this->_counterpart;
        }

        $to = $this->to();

        $from = $this->from();
        $relations = $to::definition()->relations();
        $result = [];

        foreach ($relations as $relation) {
            $rel = $to::definition()->relation($relation);
            if ($rel->to() === $this->from()) {
                $result[] = $rel;
            }
        }
        if (count($result) === 1) {
            return $this->_counterpart = reset($result);
        } elseif (count($result) > 1) {
            throw new ORMException("Ambiguous {$this->type()} counterpart relationship for `{$from}`. Apply the Single Table Inheritance pattern to get unique models.");
        }
        throw new ORMException("Missing {$this->type()} counterpart relationship for `{$from}`. Add one in the `{$to}` model.");
    }

    /**
     * Returns the "primary key/foreign key" matching definition. The key corresponds
     * to field name in the source model and the value is the one in the target model.
     *
     * @param  mixed $type An optionnal type to get.
     * @return mixed       Returns "primary key/foreign key" matching definition array or
     *                     a specific one if `$type` is not `null`.
     */
    public function keys($type = null) {
        if (!$type) {
            return $this->_keys;
        }
        if ($type === 'from') {
            return key($this->_keys);
        } elseif ($type === 'to') {
            return reset($this->_keys);
        }
        throw new ORMException("Invalid type `'{$type}'` only `'from'` and `'to'` are available");
    }

    /**
     * Generates the matching conditions for a related object (or objects) for the given object
     * connected to it by this relationship.
     *
     * @param  object $entity The entity or collection object to get the related data from.
     * @return array          Returns a conditions array.
     */
    public function match($entity)
    {
        $keys = $this->keys();
        $from = key($keys);
        $to = current($keys);

        $conditions = [];

        if (!isset($entity->{$from})) {
            throw new ORMException("The `'{$from}'` key is missing from entity data.");
        }
        $conditions[$to] = $entity->{$from};
        return $conditions;
    }

    // /**
    //  * Gets the related data.
    //  *
    //  * @param  object $entity An entity.
    //  * @return                The related data.
    //  */
    // public function get($entity, $options = [])
    // {
    //     $name = $this->name();
    //     $entity->sync();

    //     if (!$entity->exists()) {
    //         return $entity->schema()->cast($name, [], [
    //             'parent' => $entity
    //         ]);
    //     }

    //     $link = $this->link();
    //     $strategies = $this->strategies();

    //     if (!isset($strategies[$link]) || !is_callable($strategies[$link])) {
    //         throw new ORMException("Attempted to get object for invalid relationship link type `{$link}`.");
    //     }
    //     return $strategies[$link]($entity, $this, $options);
    // }

    // /**
    //  * Strategies used to query related objects.
    //  */
    // public function strategies()
    // {
    //     return [
    //         static::LINK_EMBEDDED => function($entity, $relationship) {
    //             return $entity->{$relationship->name()};
    //         },
    //         static::LINK_CONTAINED => function($entity, $relationship) {
    //             return $relationship->isMany() ? $entity->parent()->parent() : $entity->parent();
    //         },
    //         static::LINK_KEY => function($entity, $relationship, $options) {
    //             if ($relationship->type() === 'hasManyThrough') {
    //                 $collection = [$entity];
    //                 $this->embed($collection, $options);
    //                 return $entity->__get($relationship->name()); // Too Many Magic Kill The Magic.
    //             }
    //             $collection = $this->_find($entity->{$relationship->keys('from')}, $options);
    //             if ($relationship->isMany()) {
    //                 return $collection;
    //             }
    //             return count($collection) ? $collection->rewind() : null;
    //         },
    //         static::LINK_KEY_LIST  => function($object, $relationship, $options) {
    //             return $this->_find($entity->{$relationship->keys('from')}, $options);
    //         }
    //     ];
    // }

    /**
     * Get a related object (or objects) for the given object connected to it by this relationship.
     *
     * @return boolean Returns `true` if the relationship is a `'hasMany'` or `'hasManyThrough`' relation,
     *                 returns `false` otherwise.
     */
    public function isMany()
    {
        return !!preg_match('~Many~', static::class);
    }

    /**
     * Gets all entities attached to a collection en entities.
     *
     * @param  mixed  $id An id or an array of ids.
     * @return object     A collection of items matching the id/ids.
     */
    protected function _find($id, $options = [])
    {
        $defaults = [
            'query'        => [],
            'fetchOptions' => []
        ];
        $options += $defaults;

        $fetchOptions = $options['fetchOptions'];
        unset($options['fetchOptions']);

        if ($this->link() !== static::LINK_KEY) {
            throw new ORMException("This relation is not based on a foreign key.");
        }
        $to = $this->to();
        $schema = $to::definition();

        if (!$id) {
            return $to::create([], ['type' => 'set']);
        }

        $ids = is_array($id) ? $id : [$id];
        $key = $schema->key();
        $column = $schema->column($key);

        foreach ($ids as $i => $value) {
            $ids[$i] = $schema->convert('cast', $column['type'], $value, $column);
        }

        if (count($ids) === 1) {
            $ids = reset($ids);
        }
        $conditions = [$this->keys('to') => $ids];
        if ($this->conditions()) {
            $conditions = [
                ':and()' => [
                    [$this->keys('to') => $ids],
                    $this->conditions()
                ]
            ];
        }
        $query = Set::extend($options['query'], ['conditions' => $conditions]);
        return $to::all($query, $fetchOptions);
    }

    /**
     * Indexes a collection.
     *
     * @param  mixed  $collection An collection to extract index from.
     * @param  string $name       The field name to build index for.
     * @return Array              An array of indexes where keys are `$name` values and
     *                            values the corresponding index in the collection.
     */
    protected function _index($collection, $name)
    {
        $indexes = [];
        foreach ($collection as $key => $entity) {
            if (is_object($entity)) {
                if ($entity->{$name}) {
                    $indexes[(string) $entity->{$name}] = $key;
                }
            } else {
                if (isset($entity[$name])) {
                    $indexes[(string) $entity[$name]] = $key;
                }
            }
        }
        return $indexes;
    }

    /**
     * Unsets the relationship attached to a collection en entities.
     *
     * @param  mixed  $collection An collection to "clean up".
     */
    public function _cleanup(&$collection)
    {
        $name = $this->name();

        if ($this->isMany()) {
            foreach ($collection as $index => $entity) {
                if (is_object($entity)) {
                    $entity->{$name} = [];
                } else {
                    $collection[$index][$name] = [];
                }
            }
            return;
        }

        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                unset($entity->{$name});
            } else {
                unset($entity[$name]);
            }
        }
    }

    /**
     * Check if an entity is valid or not.
     *
     * @param  object  $entity  The relation's entity.
     * @param  array   $options The validation options.
     * @return boolean
     */
    public function validates($entity, $options = [])
    {
        $fieldname = $this->name();

        if (!isset($entity->{$fieldname})) {
            return true;
        }
        return $entity->{$fieldname}->validates($options);
    }
}
