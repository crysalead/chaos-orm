<?php
namespace chaos\relationship;

use chaos\ChaosException;
use chaos\Model;
use chaos\collection\Through;
use chaos\Conventions;

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
            'name'        => null,
            'correlate'   => null,
            'from'        => null,
            'through'     => null,
            'using'       => null,
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'constraints' => [],
            'conventions' => null
        ];

        $config += $defaults;

        foreach (['from', 'through', 'using'] as $value) {
            if (!$config[$value]) {
                throw new ChaosException("`'{$value}'` option can't be empty for a has many through relation.");
            }
        }

        $this->_conventions = $config['conventions'] ?: new Conventions();

        if (!$config['correlate']) {
            $config['correlate'] = $this->_conventions->apply('fieldName', $config['from']);
        }

        $this->_correlate = $config['correlate'];
        $this->_from = $config['from'];
        $this->_through = $config['through'];
        $this->_link = $config['link'];
        $this->_fields = $config['fields'];
        $this->_constraints = $config['constraints'];
        $this->_using = $config['using'];

        $from = $this->from();
        $relThrough = $from::relation($this->through());
        $pivot = $relThrough->to();
        $relUsing = $pivot::relation($this->using());

        $this->_to = $relUsing->to();
        $this->_keys = $relUsing->keys();

        $this->_name = $config['name'] ?: $this->_conventions->apply('fieldName', $this->to());

        $pos = strrpos(static::class, '\\');
        $this->_type = lcfirst(substr(static::class, $pos !== false ? $pos + 1 : 0));
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

        $from = $this->from();
        $relThrough = $from::relation($through);
        $middle = $relThrough->embed($collection, $options);

        $pivot = $relThrough->to();
        $relUsing = $pivot::schema()->relation($using);
        $related = $relUsing->embed($middle, $options);

        $this->_cleanup($collection);

        $arrayHydration = false;

        foreach ($collection as $index => $entity) {
            if (is_object($entity)) {
                $entity->{$name} = [];
            } else {
                $collection[$index][$name] = [];
                $arrayHydration = true;
            }
        }

        if ($arrayHydration) {
            $fromKey = $this->keys('from');
            $indexes = $this->_index($related, $this->keys('to'));
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
                    }
                }
            } else {
                foreach ($entity[$through] as $key => $item) {
                    if (isset($indexes[$item[$fromKey]])) {
                        $collection[$index][$name][] = $related[$indexes[$item[$fromKey]]];
                        $collection[$index][$through][$key][$using] = $related[$indexes[$item[$fromKey]]];
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
