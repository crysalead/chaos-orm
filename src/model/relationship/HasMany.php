<?php
namespace chaos\model\relationship;

use chaos\SourceException;

/**
 * The `HasMany` relationship.
 */
class HasMany extends \chaos\model\Relationship
{
    /**
     * Expands a collection of entities by adding their related data.
     *
     * @param  mixed $collection The collection to expand.
     * @return array             The collection of related entities.
     */
    public function expand(&$collection, $related)
    {
        if (!$schema = $this->schema()) {
            throw new SourceException("The `{$class}` relation is missing a `'schema'` dependency.");
        }

        $name = $this->name();
        $indexes = $this->_index($collection, $this->keys('from'));
        $this->_cleanup($collection);

        foreach ($related as $index => $entity) {
            if (is_object($entity)) {
                $value = $entity->{$this->keys('to')};
                $source = $collection[$indexes[$value]];
                if (!isset($source->{$name})) {
                    $source->{$name} = [];
                }
                $source->{$name}[] = $entity;
            } else {
                $value = $entity[$this->keys('to')];
                if (!isset($collection[$indexes[$value]][$name])) {
                    $collection[$indexes[$value]][$name] = [];
                }
                $collection[$indexes[$value]][$name][] = $entity;
            }
        }
        return $collection;
    }

    /**
     * Saving a related entity.
     *
     * @param  object  $entity The relation's entity
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
        $erase = array_fill_keys(array_keys($conditions), null);
        $to::update($erase, $conditions);
        $result = $related->set($conditions)->save($options);
        return $result;
    }
}
