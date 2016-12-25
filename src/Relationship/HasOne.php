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
        $related = $this->_find($indexes->keys(), Set::merge(['fetchOptions' => [
            'collector' => $this->_collector($collection)
        ]], $options));

        $name = $this->name();

        $this->_cleanup($collection);

        foreach ($related as $index => $entity) {
            if (is_object($entity)) {
                $value = $entity->{$this->keys('to')};
                if ($indexes->has($value)) {
                    $source = $collection[$indexes->get($value)];
                    $source->{$name} = $entity;
                }
            } else {
                $value = $entity[$this->keys('to')];
                if ($indexes->has($value)) {
                    $collection[$indexes->get($value)][$name] = $entity;
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
    public function broadcast($entity, $options = [])
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
        $result = !!$related->set($conditions)->broadcast($options);
        return $result;
    }
}
