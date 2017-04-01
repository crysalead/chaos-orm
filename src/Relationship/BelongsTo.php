<?php
namespace Chaos\ORM\Relationship;

use Lead\Set\Set;
use Chaos\ORM\ORMException;

/**
 * The `BelongsTo` relationship.
 */
class BelongsTo extends \Chaos\ORM\Relationship
{
    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @see Chaos\ORM\Relationship
     */
    public function __construct($config = [])
    {
        $keys = isset($config['keys']);

        parent::__construct($config);

        if (!$keys ) {
            $key = $this->_conventions->apply('key');
            $this->_keys = [$this->_conventions->apply('reference', $config['to']) => $key];
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
        $related = $this->_find(array_keys($indexes), $options);

        $name = $this->name();
        $indexes = $this->_index($related, $this->keys('to'));
        $this->_cleanup($collection);

        foreach ($collection as $index => $source) {
            if (is_object($source)) {
                $value = (string) $source->{$this->keys('from')};
                if (isset($indexes[$value])) {
                    $source->{$name} = $related[$indexes[$value]];
                }
            } else {
                $value = (string) $source[$this->keys('from')];
                if (isset($indexes[$value])) {
                    $collection[$index][$name] = &$related[$indexes[$value]];
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
            throw new ORMException("The `'{$to}'` key is missing from related data.");
        }
        $conditions[$from] = $related->{$to};

        $entity->set($conditions);
        return $result;
    }
}
