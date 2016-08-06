<?php
namespace Chaos\Relationship;

use Lead\Set\Set;
use Chaos\ChaosException;

/**
 * The `BelongsTo` relationship.
 */
class BelongsTo extends \Chaos\Relationship
{
    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @see Chaos\Relationship
     */
    public function __construct($config = [])
    {
        $keys = isset($config['keys']);

        parent::__construct($config);

        if (!$keys ) {
            $key = $this->_conventions->apply('key');
            $this->_keys = [$this->_conventions->apply('reference', $config['from']) => $key];
        }
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
        $related = $this->_find($indexes->keys(), Set::merge(['fetchOptions' => [
            'collector' => $this->_collector($collection)
        ]], $options));

        $name = $this->name();
        $indexes = $this->_index($related, $this->keys('to'));
        $this->_cleanup($collection);

        foreach ($collection as $index => $source) {
            if (is_object($source)) {
                $value = $source->{$this->keys('from')};
                if ($indexes->has($value)) {
                    $source->{$name} = $related[$indexes->get($value)];
                }
            } else {
                $value = $source[$this->keys('from')];
                if ($indexes->has($value)) {
                    $collection[$index][$name] = $related[$indexes->get($value)];
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
        $related = $entity->{$name};
        $result = $related->save($options);

        $keys = $this->keys();
        list($from, $to) = each($keys);

        $conditions = [];
        if (!isset($related->{$to})) {
            throw new ChaosException("The `'{$to}'` key is missing from related data.");
        }
        $conditions[$from] = $related->{$to};

        $entity->set($conditions);
        return $result;
    }
}
