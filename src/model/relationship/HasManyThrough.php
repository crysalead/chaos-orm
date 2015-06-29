<?php
namespace chaos\model\relationship;

use chaos\model\Model;
use chaos\model\collection\Through;

/**
 * The `HasManyThrough` relationship.
 */
class HasManyThrough extends \chaos\model\Relationship
{
    /**
     * The relation name of the pivot.
     *
     * @var string
     */
    protected $_through = null;

    /**
     * The relation name to use in combinaison with through option.
     *
     * @var string
     */
    protected $_using = null;

    /**
     * The saving mode strategy.
     *
     * @var string
     */
    protected $_mode = null;

    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @see chaos\model\Relationship
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'through'` _string_ : The relation name of the pivot.
     *                      - `'using'`   _string_ : The relation name to use in combinaison with through option.
     *                      - `'mode'`    _string_ : The saving mode strategy.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'through'  => null,
            'using'    => null,
            'mode'     => 'diff'
        ];
        $config += $defaults;

        if (!$config['through']) {
            throw new SourceException("Error, `'through'` option can't be empty for a has many through relation.");
        }

        parent::__construct(['to' => Model::class] + $config);

        $this->_through = $config['through'];
        $this->_using = $config['using'];
        $this->_mode = $config['mode'];

        if (!$config['using']) {
            $this->_using = $this->_conventions->apply('usingName', $this->name());
        }

        $relThrough = $this->_schema->relation($this->through());
        $pivot = $relThrough->to();
        $relUsing = $pivot::relation($this->using());

        $this->_to = $relUsing->to();
        $this->_keys = $relUsing->keys();
    }

    /**
     * Expands a collection of entities by adding their related data.
     *
     * @param  mixed $collection The collection to expand.
     * @return array             The collection of related entities.
     */
    public function embed(&$collection, $options = [])
    {
        $name = $this->name();
        $through = $this->through();
        $using = $this->using();

        $this->_cleanup($collection);
        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                $entity->{$name} = [];
            } if (is_array($entity)) {
                $collection[$index][$name] = [];
            }
        }

        foreach ($collection as $index => $source) {
            if (is_object($source)) {
                foreach ($source->{$through} as $item) {
                    $value = $item->{$using};
                    if (!$source->{$name} instanceof Through) {
                        $source->{$name}[] = $value;
                    }
                }
            } else {
                foreach ($source[$through] as $item) {
                    $collection[$index][$name][] = $item[$using];
                }
            }
        }
        return $collection;
    }

    /**
     * Gets all entities attached to a collection en entities.
     *
     * @param  mixed  $collection A collection of entities.
     */
    public function related($collection, $options = [])
    {
        $from = $collection->model();
        $relThrough = $from::relation($this->through());
        $related = $relThrough->related($collection, $options);

        $pivot = $relThrough->to();
        $relUsing = $pivot::relation($this->using());
        return $relUsing->related($related, $options);
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

        $relThrough = $entity::relation($this->through());
        $conditions = $relThrough->match($entity);
        $middle = $relThrough->to();
        $using = $this->using();
        $relTo = $middle::relation($using);
        $strategy = '_' . $this->mode();
        $result = $this->{$strategy}($related, $relTo, $relThrough, $conditions, $options);
        unset($entity->{$using});

        $conditions = $this->match($entity);
        $to = $this->to();
        $erase = array_fill_keys(array_keys($conditions), null);
        $to::update($erase, $conditions);
        $result = $related->set($conditions)->save($options);
        return $result;
    }

    /**
     * Perform a hasManyThrough diff saving (i.e only create/delete the unexisted/deleted associations)
     *
     * @param  object  $collection      The pivot collection.
     * @param  object  $relTo           The destination relation.
     * @param  object  $relVia          The middle relation.
     * @param  array   $conditionsFrom  The association data extracted from the through relation.
     * @param  array   $options         Saving options.
     * @return boolean
     */
    protected function _diff($collection, $relTo, $relThrough, $conditionsFrom, $options = [])
    {
        $return = true;
        $to = $relTo->to();
        $middle = $relThrough->to();
        $alreadySaved = $middle::find('all', ['conditions' => $conditionsFrom]);

        foreach ($collection as $entity) {
            $finded = false;
            $entity->save($options);
            $conditionsTo = $relTo->match($entity);

            foreach ($alreadySaved as $key => $value) {
                if (!array_intersect_assoc($conditionsTo, $value)) {
                    continue;
                }
                $finded = true;
                unset($alreadySaved[$key]);
                break;
            }

            if (!$finded) {
                $item = $middle::create($conditionsFrom + $relTo->match($entity));
                $return &= $item->save($options);
            }
        }

        $toDelete = [];

        foreach ($alreadySaved as $key => $entity) {
            $toDelete[] = $entity->primaryKey();
        }

        if ($toDelete) {
            $primaryKey = $entity::schema()->primaryKey();
            $return &= $middle::remove([$primaryKey => $toDelete]);
        }
        return true;
    }

    /**
     * Perform a hasManyThrough flush saving (i.e remove & recreate all associations)
     *
     * @param  object  $collection      The pivot collection.
     * @param  object  $relTo           The destination relation.
     * @param  object  $relVia          The middle relation.
     * @param  array   $conditionsFrom  The association data extracted from the through relation.
     * @param  array   $options         Saving options.
     * @return boolean
     */
    protected function _flush($collection, $relTo, $relThrough, $conditionsFrom, $options = [])
    {
        $return = true;
        $to = $relTo->to();
        $middle = $relThrough->to();
        $return &= $middle::remove($conditionsFrom);
        foreach ($collection as $entity) {
            $entity->save($options);
            $item = $middle::create($conditionsFrom + $relTo->match($entity));
            $return &= $item->save($options);
        }
        return $return;
    }
}
