<?php
namespace chaos\model\relationship;

/**
 * The `HasMany` relationship.
 */
class HasMany extends \chaos\model\Relationship
{

    public function expand($collection)
    {

    }

    /**
     * Saving an entity relation.
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
