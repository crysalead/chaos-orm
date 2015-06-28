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
    public function embed(&$collection, $options = [])
    {
        if (!$schema = $this->schema()) {
            throw new SourceException("The `{$class}` relation is missing a `'schema'` dependency.");
        }

        $related = $this->related($collection, $options);

        $name = $this->name();
        $indexes = $this->_index($collection, $this->keys('from'));
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
