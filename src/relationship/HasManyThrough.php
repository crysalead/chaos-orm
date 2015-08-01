<?php
namespace chaos\relationship;

use chaos\ChaosException;
use chaos\Model;
use chaos\collection\Through;

/**
 * The `HasManyThrough` relationship.
 */
class HasManyThrough extends \chaos\Relationship
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
     * @see chaos\Relationship
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
            throw new ChaosException("Error, `'through'` option can't be empty for a has many through relation.");
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

        $relThrough = $this->_schema->relation($through);
        $middle = $relThrough->embed($collection, $options);

        $pivot = $relThrough->to();
        $relUsing = $pivot::schema()->relation($using);
        $related = $relUsing->embed($middle, $options);

        $this->_cleanup($collection);

        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                $entity->{$name} = [];
            } else {
                $collection[$index][$name] = [];
            }
        }

        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                foreach ($entity->{$through} as $key => $item) {
                    if (isset($item->{$using})) {
                        $value = $item->{$using};
                        if ($entity instanceof Model) {
                            $entity->__get($name); // Too Many Magic Kill The Magic.
                        } else {
                            $entity->{$name}[] = $value;
                        }
                    } else {
                        unset($entity->{$through}[$key]);
                    }
                }
            } else {
                foreach ($entity[$through] as $key => $item) {
                    if (isset($item[$using])) {
                        $collection[$index][$name][] = $item[$using];
                    } else {
                        unset($entity[$through][$key]);
                    }
                }
            }
        }
        return $related;
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
        return true;
    }

    /**
     * Validating an entity relation.
     *
     * @param  object  $entity The relation's entity
     * @param  array   $options Saving options.
     * @return boolean
     */
    public function validate($entity, $options = [])
    {
        return true;
    }
}
