<?php
namespace Chaos\ORM\Relationship;

use Traversable;
use Lead\Set\Set;
use Chaos\ORM\Document;

/**
 * The `HasMany` relationship.
 */
class HasMany extends \Chaos\ORM\Relationship
{
    /**
     * Indicates whether the relation is a junction table or not.
     *
     * @var boolean
     */
    protected $_junction = false;

    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @see Chaos\ORM\Relationship
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'junction'` _boolean_ : Indicates whether the relation is a junction table or not.
     *                                                 If `true`, associative entities are removed when unsetted.
     *                                                 All `hasMany` relations used aby an `hasManyThrough` relation will
     *                                                 have their junction attribute set to `true`. Default to `false`.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'junction' => false
        ];

        $config += $defaults;

        parent::__construct($config);

        $this->_junction = $config['junction'];
    }

    /**
     * Sets the behavior of associative entities. If a hasMany relation is marked as a "junction table",
     * associative entities will be removed once a foreign key is unsetted. When a hasMany relation is
     * not marked as a "junction table", associative entities will simply have their foreign key unsetted.
     *
     * @param  boolean $boolean The junction value to set or none to get it.
     * @return object           Returns `$this` on set and the junction value on get.
     */
    public function junction($boolean = null)
    {
        if (func_num_args()) {
            $this->_junction = $boolean;
            return $this;
        }
        return $this->_junction;
    }

    /**
     * Expands a collection of entities by adding their related data.
     *
     * @param  mixed $collection The collection to expand.
     * @param  array $options    The embedging options.
     * @return array             The collection of related entities.
     */
    public function embed(&$collection, $options = [])
    {
        $indexes = $this->_index($collection, $this->keys('from'));
        $related = $this->_find(array_keys($indexes), $options);

        $name = $this->name();
        $this->_cleanup($collection);

        foreach ($related as $index => $entity) {
            $values = is_object($entity) ? $entity->{$this->keys('to')} : $entity[$this->keys('to')];
            $values = is_array($values) || $values instanceof Traversable ? $values : [$values];
            foreach ($values as $value) {
                $value = (string) $value;
                if (isset($indexes[$value])) {
                    if (is_object($collection[$indexes[$value]])) {
                        $source = $collection[$indexes[$value]];
                        if ($entity instanceof Document) {
                            $source->__get($name)->push($entity); // Too Many Magic Kill The Magic.
                        } else {
                            $source->{$name}[] = $entity;
                        }
                    } else {
                        $collection[$indexes[$value]][$name][] = &$related[$index];
                    }
                }
            }
        }
        return $related;
    }

    /**
     * Saves a relation.
     *
     * @param  object  $entity  The relation's entity
     * @param  array   $options Saving options.
     * @return boolean
     */
    public function save($entity, $options = [])
    {
        if ($this->link() !== static::LINK_KEY) {
            return true;
        }

        $name = $this->name();
        if (!isset($entity->{$name})) {
            return true;
        }

        $conditions = $this->match($entity) + $this->conditions();
        $to = $this->to();
        $previous = $to::all(['conditions' => $conditions]);

        $indexes = $this->_index($previous, $this->keys('from'));
        $result = true;

        foreach ($entity->{$name} as $item) {
            $item->sync();
            $value = (string) $item->id();
            if ($item->exists() && isset($indexes[$value])) {
                unset($previous[$indexes[$value]]);
            }
            $item->set($conditions);
            $result = $result && $item->save($options);
        }

        $junction = $this->junction();

        if ($junction) {
            foreach ($previous as $deprecated) {
                $deprecated->delete();
            }
        } else {
            $toKey = $this->keys('to');
            foreach ($previous as $deprecated) {
                unset($deprecated->{$toKey});
                $deprecated->save();
            }
        }

        return $result;
    }
}
