<?php
namespace Chaos\ORM\Relationship;

use Lead\Set\Set;
use Chaos\ORM\ORMException;
use Chaos\ORM\Model;
use Chaos\ORM\Collection\Through;
use Chaos\ORM\Conventions;

/**
 * The `HasManyThrough` relationship.
 */
class HasManyThrough extends \Chaos\ORM\Relationship
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
     * @see Chaos\ORM\Relationship
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'name'`        _string_ : The field name used for accessing the related data.
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name defaults to `'comments'`.
     *                      - `'from'`        _string_ : The fully namespaced class name this relationship originates.
     *                      - `'through'`     _string_ : The relation name of the pivot.
     *                      - `'using'`       _string_ : The relation name to use in combinaison with through option.
     *                      - `'link'`        _string_ : A constant specifying how the object bound to the originating
     *                                                   model is linked to the object bound to the target model. For relational
     *                                                   databases, the only valid value is `LINK_KEY`, which means a foreign
     *                                                   key in one object matches another key (usually the primary key) in the other.
     *                                                   For document-oriented and other non-relational databases, different types of
     *                                                   linking, including key lists or even embedding.
     *                      - `'fields'`      _mixed_  : An array of the subset of fields that should be selected
     *                                                   from the related object(s) by default. If set to `true` (the default), all
     *                                                   fields are selected.
     *                      - `'conventions'` _object_ : The naming conventions instance to use.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'        => null,
            'from'        => null,
            'through'     => null,
            'using'       => null,
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'conventions' => null
        ];

        $config += $defaults;

        foreach (['from', 'through', 'using'] as $value) {
            if (!$config[$value]) {
                throw new ORMException("The relationship `'{$value}'` option can't be empty.");
            }
        }

        $this->_conventions = $config['conventions'] ?: new Conventions();

        $this->_from = $config['from'];
        $this->_through = $config['through'];
        $this->_link = $config['link'];
        $this->_fields = $config['fields'];
        $this->_using = $config['using'];

        $from = $this->from();
        $relThrough = $from::definition()->relation($this->through());
        $relThrough->junction(true);
        $pivot = $relThrough->to();
        $relUsing = $pivot::definition()->relation($this->using());

        $this->_to = $relUsing->to();
        $this->_keys = $relUsing->keys();

        $this->_name = $config['name'] ?: $this->_conventions->apply('field', $this->to());

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
        $options = Set::merge(['fetchOptions' => [
            'collector' => $this->_collector($collection)
        ]], $options);

        $name = $this->name();
        $through = $this->through();
        $using = $this->using();

        $from = $this->from();
        $relThrough = $from::definition()->relation($through);
        $middle = $relThrough->embed($collection, $options);

        $pivot = $relThrough->to();
        $relUsing = $pivot::definition()->relation($using);
        $related = $relUsing->embed($middle, $options);

        $this->_cleanup($collection);

        $fromKey = $this->keys('from');
        $indexes = $this->_index($related, $this->keys('to'));

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
                    if ($indexes->has($item[$fromKey])) {
                        $collection[$index][$name][] = $related[$indexes->get($item[$fromKey])];
                        $collection[$index][$through][$key][$using] = $related[$indexes->get($item[$fromKey])];
                    }
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
    public function broadcast($entity, $options = [])
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
    public function validates($entity, $options = [])
    {
        return true;
    }
}
