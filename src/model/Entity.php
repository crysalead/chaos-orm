<?php
namespace chaos\model;

class Entity
{
    /**
     * Default query parameters for the model finders.
     *
     * - `'conditions'`: The conditional query elements, e.g.
     *                 `'conditions' => array('published' => true)`
     * - `'fields'`: The fields that should be retrieved. When set to `null`, defaults to
     *             all fields.
     * - `'order'`: The order in which the data will be returned, e.g. `'order' => 'ASC'`.
     * - `'limit'`: The maximum number of records to return.
     * - `'page'`: For pagination of data.
     * - `'with'`: An array of relationship names to be included in the query.
     *
     * @var array
     */
    protected $_query = [
        'conditions' => null,
        'fields'     => null,
        'order'      => null,
        'limit'      => null,
        'page'       => null,
        'with'       => []
    ];

/*
    protected static function _rules()
    {
        return [
            'username' => [
                ['notEmpty', 'message' => 'E-mail cannot be empty.'],
                ['email', 'message' => 'E-mail is not valid.'],
                [
                    'unique', 'fields' => ['username'],
                    'message' => 'Sorry, this e-mail address is already registered.'
                ],
            ],
            'name' => [
                ['notEmpty', 'message' => 'Name cannot be empty.', 'required' => false]
            ],
            'password' => [
                ['notEmpty', 'message' => 'Password cannot be empty.', 'required' => false],
                ['notEmptyHash', 'message' => 'Password cannot be empty.', 'required' => false]
            ],
            'passwordConfirm' => [
                [
                    'matchesPassword', 'field' => 'password', 'required' => false,
                    'message' => 'Your passwords must match.'
                ]
            ]
        ];
    }

    protected static function _fields()
    {
        return [
            'id' => ['type' => 'int', 'primary' => true, 'serial' => true],
            'title' => ['type' => 'string', 'required' => true],
            'body' => ['type' => 'text', 'required' => true],
            'status' => ['type' => 'int', 'default' => 0, 'index' => true],
            'date_created' => ['type' => 'datetime']
        ];
    }

    protected static function _relations()
    {
        return [
            'comments' => [
                'type' => 'HasMany',
                'to' => 'Comment',
                'find' => []
            ]
        ];
    }
*/


    /**
     * If no values supplied, returns the name of the `Model` key. If values
     * are supplied, returns the key value.
     *
     * @param mixed $values An array of values or object with values. If `$values` is `null`,
     *              the meta `'key'` of the model is returned.
     * @return mixed Key value.
     */
    public static function key($values = null) {
    }

    /**
     * Returns a list of models related to `Model`, or a list of models related
     * to this model, but of a certain type.
     *
     * @param string $type A type of model relation.
     * @return mixed An array of relation instances or an instance of relation.
     */
    public static function relations($type = null) {
        $self = static::_object();

        if ($type === null) {
            return static::_relations();
        }

        if (isset($self->_relationFieldNames[$type])) {
            $type = $self->_relationFieldNames[$type];
        }

        if (isset($self->_relations[$type])) {
            return $self->_relations[$type];
        }

        if (isset($self->_relationsToLoad[$type])) {
            return static::_relations(null, $type);
        }

        if (in_array($type, $self->_relationTypes, true)) {
            return array_keys(static::_relations($type));
        }
        return null;
    }

    /**
     * This method automagically bind in the fly unloaded relations.
     *
     * @see lithium\data\model::relations()
     * @param $type A type of model relation.
     * @param $name A relation name.
     * @return An array of relation instances or an instance of relation.
     */
    protected static function _relations($type = null, $name = null) {
        $self = static::_object();

        if ($name) {
            if (isset($self->_relationsToLoad[$name])) {
                $t = $self->_relationsToLoad[$name];
                unset($self->_relationsToLoad[$name]);
                return static::bind($t, $name, (array) $self->{$t}[$name]);
            }
            return isset($self->_relations[$name]) ? $self->_relations[$name] : null;
        }
        if (!$type) {
            foreach ($self->_relationsToLoad as $name => $t) {
                static::bind($t, $name, (array) $self->{$t}[$name]);
            }
            $self->_relationsToLoad = array();
            return $self->_relations;
        }
        foreach ($self->_relationsToLoad as $name => $t) {
            if ($type === $t) {
                static::bind($t, $name, (array) $self->{$t}[$name]);
                unset($self->_relationsToLoad[$name]);
            }
        }
        return array_filter($self->_relations, function($i) use ($type) {
            return $i->data('type') === $type;
        });
    }


    /**
     * Iterates through relationship types to construct relation map.
     *
     * @return void
     * @todo See if this can be rewritten to be lazy.
     */
    protected static function _relationsToLoad() {
        try {
            if (!$connection = static::connection()) {
                return;
            }
        } catch (ConfigExcepton $e) {
            return;
        }

        if (!$connection::enabled('relationships')) {
            return;
        }

        $self = static::_object();

        foreach ($self->_relationTypes as $type) {
            $self->$type = Set::normalize($self->$type);
            foreach ($self->$type as $name => $config) {
                $self->_relationsToLoad[$name] = $type;
                $fieldName = $self->_relationFieldName($type, $name);
                $self->_relationFieldNames[$fieldName] = $name;
            }
        }
    }

    /**
     * Creates a relationship binding between this model and another.
     *
     * @see lithium\data\model\Relationship
     * @param string $type The type of relationship to create. Must be one of `'hasOne'`,
     *               `'hasMany'` or `'belongsTo'`.
     * @param string $name The name of the relationship. If this is also the name of the model,
     *               the model must be in the same namespace as this model. Otherwise, the
     *               fully-namespaced path to the model class must be specified in `$config`.
     * @param array $config Any other configuration that should be specified in the relationship.
     *              See the `Relationship` class for more information.
     * @return object Returns an instance of the `Relationship` class that defines the connection.
     */
    public static function bind($type, $name, array $config = array()) {
        $self = static::_object();
        if (!isset($config['fieldName'])) {
            $config['fieldName'] = $self->_relationFieldName($type, $name);
        }

        if (!in_array($type, $self->_relationTypes)) {
            throw new ConfigException("Invalid relationship type `{$type}` specified.");
        }
        $self->_relationFieldNames[$config['fieldName']] = $name;
        $rel = static::connection()->relationship(get_called_class(), $type, $name, $config);
        return $self->_relations[$name] = $rel;
    }

    /**
     * An important part of describing the business logic of a model class is defining the
     * validation rules. In Lithium models, rules are defined through the `$validates` class
     * property, and are used by this method before saving to verify the correctness of the data
     * being sent to the backend data source.
     *
     * Note that these are application-level validation rules, and do not
     * interact with any rules or constraints defined in your data source. If such constraints fail,
     * an exception will be thrown by the database layer. The `validates()` method only checks
     * against the rules defined in application code.
     *
     * This method uses the `Validator` class to perform data validation. An array representation of
     * the entity object to be tested is passed to the `check()` method, along with the model's
     * validation rules. Any rules defined in the `Validator` class can be used to validate fields.
     * See the `Validator` class to add custom rules, or override built-in rules.
     *
     * @see lithium\data\Model::$validates
     * @see lithium\util\Validator::check()
     * @see lithium\data\Entity::errors()
     * @param string $entity Model entity to validate. Typically either a `Record` or `Document`
     *        object. In the following example:
     *        {{{
     *            $post = Posts::create($data);
     *            $success = $post->validates();
     *        }}}
     *        The `$entity` parameter is equal to the `$post` object instance.
     * @param array $options Available options:
     *        - `'rules'` _array_: If specified, this array will _replace_ the default
     *          validation rules defined in `$validates`.
     *        - `'events'` _mixed_: A string or array defining one or more validation
     *          _events_. Events are different contexts in which data events can occur, and
     *          correspond to the optional `'on'` key in validation rules. For example, by
     *          default, `'events'` is set to either `'create'` or `'update'`, depending on
     *          whether `$entity` already exists. Then, individual rules can specify
     *          `'on' => 'create'` or `'on' => 'update'` to only be applied at certain times.
     *          Using this parameter, you can set up custom events in your rules as well, such
     *          as `'on' => 'login'`. Note that when defining validation rules, the `'on'` key
     *          can also be an array of multiple events.
     * @return boolean Returns `true` if all validation rules on all fields succeed, otherwise
     *         `false`. After validation, the messages for any validation failures are assigned to
     *         the entity, and accessible through the `errors()` method of the entity object.
     * @filter
     */
    public function validates($entity, array $options = array()) {
        $defaults = array(
            'rules' => $this->validates,
            'events' => $entity->exists() ? 'update' : 'create',
            'model' => get_called_class(),
            'with' => false
        );
        $options += $defaults;
        $self = static::_object();
        $validator = $self->_classes['validator'];
        $entity->errors(false);
        $params = compact('entity', 'options');

        if ($with = static::_withRelations($options['with'])) {
            $model = $entity->model();
            foreach ($with as $relName => $value) {
                $rel = $model::relations($relName);
                $errors = $rel->validates($entity->$relName, array('with' => $value) + $options);
                if ($errors && !array_filter($errors)) {
                    return false;
                }
            }
        }

        $filter = function($parent, $params) use ($validator) {
            $entity = $params['entity'];
            $options = $params['options'];
            $rules = $options['rules'];
            unset($options['rules']);

            if ($errors = $validator::check($entity->data(), $rules, $options)) {
                $entity->errors($errors);
            }
            return empty($errors);
        };
        return static::_filter(__FUNCTION__, $params, $filter);
    }

    protected static function _withRelations($with) {
        if (!$with) {
            return  false;
        }
        if ($with === true) {
            $with = array_fill_keys(array_keys(static::relations()), true);
        } else {
            $with = Set::expand(Set::normalize((array) $with));
        }
        return $with;
    }

}
