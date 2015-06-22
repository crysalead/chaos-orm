<?php
namespace chaos\model;

use chaos\SourceException;
use set\Set;

/**
 * The `Relationship` class encapsulates the data and functionality necessary to link two model
 * classes together.
 */
class Relationship
{
    /**
     * A one-to-one or many-to-one relationship in which a key contains an ID value linking to
     * another document or record.
     */
    const LINK_KEY = 'key';

    /**
     * A many-to-many relationship in which a key contains an embedded array of IDs linking to other
     * records or documents.
     */
    const LINK_KEY_LIST = 'keylist';

    /**
     * A relationship linking type defined by one document or record (or multiple) being embedded
     * within another.
     */
    const LINK_EMBEDDED = 'embedded';

    /**
     * The reciprocal of `LINK_EMBEDDED`, this defines a linking type wherein an embedded document
     * references the document that contains it.
     */
    const LINK_CONTAINED = 'contained';

    /**
     * The field name used for accessing the related data.
     *
     * @var string
     */
    protected $_name = null;

    /**
     * The correlated name of the field name in the related entity.
     *
     * @var string
     */
    protected $_correlate = null;

    /**
     * Mathing keys definition.
     *
     * @var array
     */
    protected $_keys = [];

    /**
     * The fully namespaced class name this relationship originates.
     *
     * @var string
     */
    protected $_from = null;

    /**
     * The fully namespaced class name this relationship targets.
     *
     * @var string
     */
    protected $_to = null;

    /**
     * The type of linking.
     *
     * @var string
     */
    protected $_link = null;

    /**
     * The field names to filter on.
     *
     * @var mixed
     */
    protected $_fields = true;

    /**
     * The conditions to filter on.
     *
     * @var array
     */
    protected $_constraints = [];

    /**
     * The naming conventions instance to use.
     *
     * @var object
     */
    protected $_conventions = null;



    /**
     * Constructs an object that represents a relationship between two model classes.
     *
     * @param array $config The relationship's configuration, which defines how the two models in
     *                      question are bound. The available options are:
     *                      - `'name'`        _string_ : The field name used for accessing the related data.
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name defaults to `'comments'`.
     *                      - `'correlate'`   _string_ : The correlated name of the field name in the related entity.
     *                                                   For example, in the case of `Post` hasMany `Comment`, the name defaults to `'post'`.
     *                      - `'keys'`        _mixed_  : Mathing keys definition, where the key is the key in the originating model,
     *                                                   and the value is the key in the target model (i.e. `['fromId' => 'toId']`).
     *                      - `'from'`        _string_ : The fully namespaced class name this relationship originates.
     *                      - `'to'`          _string_ : The fully namespaced class name this relationship targets.
     *                      - `'link'`        _string_ : A constant specifying how the object bound to the originating
     *                                                   model is linked to the object bound to the target model. For relational
     *                                                   databases, the only valid value is `LINK_KEY`, which means a foreign
     *                                                   key in one object matches another key (usually the primary key) in the other.
     *                                                   For document-oriented and other non-relational databases, different types of
     *                                                   linking, including key lists or even embedding.
     *                      - `'fields'`      _mixed_  : An array of the subset of fields that should be selected
     *                                                   from the related object(s) by default. If set to `true` (the default), all
     *                                                   fields are selected.
     *                      - `'constraints'` _mixed_  : A string or array containing additional constraints
     *                                                   on the relationship association. If a string, can contain a literal SQL fragment or
     *                                                   other database-native value. If an array, maps fields from the related object
     *                                                   either to fields elsewhere, or to arbitrary expressions. In either case, _the
     *                                                   values specified here will be literally interpreted by the database_.
     *                      - `'conventions'` _object_ : The naming conventions instance to use.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'name'        => null,
            'correlate'   => null,
            'keys'        => null,
            'from'        => null,
            'to'          => null,
            'link'        => static::LINK_KEY,
            'fields'      => true,
            'constraints' => [],
            'conventions' => null
        ];
        $config += $defaults;

        foreach (['from', 'to'] as $value) {
            if (!$config[$value]) {
                throw new SourceException("Error, `'{$value}'` option can't be empty.");
            }
        }

        $this->_conventions = $config['conventions'] ?: new Conventions();

        if (!$config['keys']) {
            $primaryKey = $this->_conventions->apply('primaryKey');
            $config['keys'] = [$primaryKey => $this->_conventions->apply('foreignKey', $config['from'])];
        }

        if (!$config['correlate']) {
            $config['correlate'] = $this->_conventions->apply('fieldName', $config['from']);
        }

        if (!$config['name']) {
            $config['name'] = $this->_conventions->apply('fieldName', $config['to']);
        }

        $this->_name = $config['name'];
        $this->_correlate = $config['correlate'];
        $this->_keys = $config['keys'];
        $this->_from = $config['from'];
        $this->_to = $config['to'];
        $this->_link = $config['link'];
        $this->_fields = $config['fields'];
        $this->_constraints = $config['constraints'];

    }

    /**
     * Allows relationship configuration items to be queried by name as methods.
     *
     * @param  string $name The name of the configuration item to query.
     * @param  array  $args Unused.
     * @return mixed        Returns the value of the given configuration item.
     */
    public function __call($name, $args = array())
    {
        $attribute = "_{$name}";
        return isset($this->{$attribute}) ? $this->{$attribute} : null;
    }

    /**
     * Get a related object (or objects) for the given object connected to it by this relationship.
     *
     * @return boolean Returns `true` if the relationship is a `'hasMany'` or `'hasManyThrough`' relation,
     *                 returns `false` otherwise.
     */
    public function isMany()
    {
        return preg_match('~Many~', $this->type());
    }

    /**
     * Generates the matching conditions for a related object (or objects) for the given object
     * connected to it by this relationship.
     *
     * @param  object $entity The entity or collection object to get the related data from.
     * @return array          Returns a conditions array.
     */
    public function match($entity)
    {
        list($from, $to) = each($this->keys());

        $conditions = [];

        if (!isset($entity->{$from})) {
            throw new SourceException("The `'{$from}'` key is missing from entity data.");
        }
        $conditions[$to] = $entity->{$from};
        return $conditions;
    }

    /**
     * Validates an entity relation.
     *
     * @param  object  $entity The relation's entity
     * @param  array   $options Validates option.
     * @return boolean
     */
    public function validates($entity, $options = [])
    {
        $defaults = ['with' => false];
        $name = $this->name();
        if (!isset($entity->{$name})) {
            return [true];
        }
        return (array) $entity->{$name}->validates($options + $defaults);
    }

    public function _keyIndex($collection)
    {
        $indexes = [];
        $primaryKey = $this->primaryKey();
        foreach ($collection as $key => $entity) {
            if (is_object($entity)) {
                $indexes[$entity->{$primaryKey}] = $key;
            } else {
                $indexes[$entity[$primaryKey]] = $key;
            }
        }
        return $indexes;
    }
}
