<?php
namespace chaos\model\relationship;

/**
 * The `BelongsTo` relationship.
 */
class BelongsTo extends \chaos\model\Relationship
{
    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @see chaos\model\Relationship
     */
    public function __construct($config = [])
    {
        $keys = isset($config['keys']);

        parent::__construct($config);

        if (!$keys ) {
            $primaryKey = $this->_conventions->apply('primaryKey');
            $this->_keys = [$this->_conventions->apply('foreignKey', $config['from']) => $primaryKey];
        }
    }

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
        $indexes = $this->_index($related, $this->keys('to'));
        $this->_cleanup($collection);

        foreach ($collection as $index => $source) {
            if (is_object($source)) {
                $value = $source->{$this->keys('from')};
                if (isset($indexes[$value])) {
                    $source->{$name} = $related[$indexes[$value]];
                }
            } else {
                $value = $source[$this->keys('from')];
                if (isset($indexes[$value])) {
                    $collection[$index][$name] = $related[$indexes[$value]];
                }
            }
        }
        return $collection;
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

        $related = $entity->{$name};
        $result = $related->save($options);
        $conditions = $this->match($related);
        $entity->set($conditions);
        return $result;
    }
}
