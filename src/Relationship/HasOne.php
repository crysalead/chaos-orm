<?php
namespace Chaos\ORM\Relationship;

use Lead\Set\Set;

/**
 * The `HasOne` relationship.
 */
class HasOne extends \Chaos\ORM\Relationship
{
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
            if (is_object($entity)) {
                $value = (string) $entity->{$this->keys('to')};
                if (isset($indexes[$value])) {
                    $source = $collection[$indexes[$value]];
                    $source->{$name} = $entity;
                }
            } else {
                $value = $entity[$this->keys('to')];
                if (isset($indexes[$value])) {
                    $collection[$indexes[$value]][$name] = &$related[$index];
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

        $conditions = $this->match($entity);
        $related = $entity->{$name};
        $result = !!$related->set($conditions)->save($options);
        return $result;
    }
}
