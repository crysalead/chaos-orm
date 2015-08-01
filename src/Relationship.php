<?php
namespace chaos;

use set\Set;
use chaos\ChaosException;

/**
 * The `Relationship` class encapsulates the data and functionality necessary to link two model
 * classes together.
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
     * The field name used for accessing the related data.
     *
     * @var string
     */
    protected $_name = null;

    /**
     * The correlated name of the field name in the related entity.
     *
     * @var string
     */
    protected $_correlate = null;

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
    protected $_constraints = [];

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
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name defaults to `'comments'`.
     *                      - `'correlate'`   _string_ : The correlated name of the field name in the related entity.
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name defaults to `'post'`.
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
     *                      - `'constraints'` _mixed_  : A string or array containing additional constraints
     *                                                   on the relationship association. If a string, can contain a literal SQL fragment or
     *                                                   other database-native value. If an array, maps fields from the related object
     *                                                   either to fields elsewhere, or to arbitrary expressions. In either case, _the
     *                                                   values specified here will be literally interpreted by the database_.
     *                      - `'schema'`      _object_ : A schema instance.
     *                      - `'conventions'` _object_ : The naming conventions instance to use.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'        => null,
            'correlate'   => null,
            'keys'        => null,
            'from'        => null,
            'to'          => null,
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'constraints' => [],
            'conventions' => null
        ];

        $config += $defaults;

        foreach (['from', 'to'] as $value) {
            if (!$config[$value]) {
                throw new ChaosException("Error, `'{$value}'` option can't be empty.");
            }
        }

        $this->_conventions = $config['conventions'] ?: new Conventions();

        if (!$config['keys']) {
            $primaryKey = $this->_conventions->apply('primaryKey');
            $config['keys'] = [$primaryKey => $this->_conventions->apply('foreignKey', $config['from'])];
        }

        if (!$config['correlate']) {
            $config['correlate'] = $this->_conventions->apply('fieldName', $config['from']);
        }

        if (!$config['name']) {
            $config['name'] = $this->_conventions->apply('fieldName', $config['to']);
        }

        $this->_name = $config['name'];
        $this->_correlate = $config['correlate'];
        $this->_keys = $config['keys'];
        $this->_from = $config['from'];
        $this->_to = $config['to'];
        $this->_link = $config['link'];
        $this->_fields = $config['fields'];
        $this->_constraints = $config['constraints'];

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
    public function __call($name, $args = array())
    {
        $attribute = "_{$name}";
        return isset($this->{$attribute}) ? $this->{$attribute} : null;
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
        throw new ChaosException("Invalid type `'{$type}'` only `'from'` and `'to'` are available");
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
        list($from, $to) = each($keys);

        $conditions = [];

        if (!isset($entity->{$from})) {
            throw new ChaosException("The `'{$from}'` key is missing from entity data.");
        }
        $conditions[$to] = $entity->{$from};
        return $conditions;
    }

    /**
     * Gets the related data.
     *
     * @param  $entity An entity.
     * @return         The related data.
     */
    public function get($entity, $options = [])
    {
        $name = $this->name();

        if (!$entity->exists()) {
            return $entity[$name] = $entity::schema()->cast($name, [], [
                'parent'   => $entity,
                'model'    => $this->to()
            ]);
        }

        $link = $this->link();
        $strategies = $this->strategies();

        if (!isset($strategies[$link]) || !is_callable($strategies[$link])) {
            throw new ChaosException("Attempted to get object for invalid relationship link type `{$link}`.");
        }
        return $strategies[$link]($entity, $this, $options);
    }

    /**
     * Strategies used to query related objects, indexed by key.
     */
    public function strategies()
    {
        return [
            static::LINK_EMBEDDED => function($entity, $relationship) {
                return $entity->{$relationship->name()};
            },
            static::LINK_CONTAINED => function($entity, $relationship) {
                return $relationship->isMany() ? $entity->parent()->parent() : $entity->parent();
            },
            static::LINK_KEY => function($entity, $relationship, $options) {
                if ($relationship->type() === 'hasManyThrough') {
                    $collection = [$entity];
                    $this->embed($collection, $options);
                    return $entity->__get($relationship->name()); // Too Many Magic Kill The Magic.
                }
                $collection = $this->_find($entity->{$relationship->keys('from')}, $options);
                if ($relationship->isMany()) {
                    return $collection;
                }
                return $collection ? reset($collection) : null;
            },
            static::LINK_KEY_LIST  => function($object, $relationship, $options) {
                return $this->_find($entity->{$relationship->keys('from')}, $options);
            }
        ];
    }

    /**
     * Get a related object (or objects) for the given object connected to it by this relationship.
     *
     * @return boolean Returns `true` if the relationship is a `'hasMany'` or `'hasManyThrough`' relation,
     *                 returns `false` otherwise.
     */
    public function isMany()
    {
        return preg_match('~Many~', static::class);
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
            'handler'      => null,
            'query'        => [],
            'fetchOptions' => []
        ];
        $options += $defaults;

        if ($this->link() !== static::LINK_KEY) {
            throw new ChaosException("This relation is not based on a foreign key.");
        }
        if (!$id) {
            return [];
        }
        $to = $this->to();
        $options['query'] = Set::merge($options['query'], ['conditions' => [$this->keys('to') => $id]]);
        return $to::all($options, $options['fetchOptions']);
    }

    /**
     * Indexes a collection.
     *
     * @param  mixed  $collection An collection to extract index from.
     * @param  string $name       The field name to build index for.
     * @return array              An array of indexes where keys are `$name` values and
     *                            values the correcponding index in the collection.
     */
    protected function _index($collection, $name)
    {
        $indexes = [];
        foreach ($collection as $key => $entity) {
            if (is_object($entity)) {
                if ($entity->{$name}) {
                    $indexes[$entity->{$name}] = $key;
                }
            } else {
                if (isset($entity[$name])) {
                    $indexes[$entity[$name]] = $key;
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
    public function _cleanup($collection)
    {
        $name = $this->name();
        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                unset($entity->{$name});
            } else {
                unset($entity[$name]);
            }
        }
    }

    /**
     * Validating an entity relation.
     *
     * @param  object  $entity The relation's entity
     * @param  array   $options Saving options.
     * @return boolean
     */
    public function validate($entity, $options = [])
    {
        $fieldname = $this->name();

        if (!isset($entity->{$fieldname})) {
            return true;
        }
        return $entity->{$fieldname}->validate($options);
    }
}
