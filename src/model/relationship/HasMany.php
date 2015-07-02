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

        $indexes = $this->_index($collection, $this->keys('from'));
        $related = $this->_find(array_keys($indexes), $options);

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
        $previous = $to::all(['conditions' => $conditions]);

        $indexes = $this->_index($previous, $this->keys('to'));

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
