<?php
namespace chaos\relationship;

use set\Set;
use chaos\ChaosException;

/**
 * The `HasMany` relationship.
 */
class HasMany extends \chaos\Relationship
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
        $related = $this->_find(array_keys($indexes), Set::merge(['fetchOptions' => [
            'collector' => $this->_collector($collection)
        ]], $options));

        $name = $this->name();

        $this->_cleanup($collection);

        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                $entity->{$name} = [];
            } else {
                $collection[$index][$name] = [];
            }
        }

        foreach ($related as $index => $entity) {
            if (is_object($entity)) {
                $value = $entity->{$this->keys('to')};
                if (isset($indexes[$value])) {
                    $source = $collection[$indexes[$value]];
                    $source->{$name}[] = $entity;
                }
            } else {
                $value = $entity[$this->keys('to')];
                if (isset($indexes[$value])) {
                    $collection[$indexes[$value]][$name][] = $entity;
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
        $to = $this->to();
        $previous = $to::all(['conditions' => $conditions]);

        $indexes = $this->_index($previous, $this->keys('from'));
        $result = true;

        foreach ($entity->{$name} as $item) {
            if ($item->exists() && isset($indexes[$item->primaryKey()])) {
                unset($previous[$indexes[$item->primaryKey()]]);
            }
            $item->set($conditions);
            $result = $result && $item->save($options);
        }

        foreach ($previous as $deprecated) {
            $deprecated->delete();
        }

        return $result;
    }
}
