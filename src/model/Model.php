<?php
namespace chaos\model;

class Model
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

    protected static function _validates($validates = [])
    {
        $this->_
    }

    /**
     * Instantiates a new record or document object, initialized with any data passed in. For
     * example:
     *
     * {{{
     * $post = Posts::create(array('title' => 'New post'));
     * echo $post->title; // echoes 'New post'
     * $success = $post->save();
     * }}}
     *
     * Note that while this method creates a new object, there is no effect on the database until
     * the `save()` method is called.
     *
     * In addition, this method can be used to simulate loading a pre-existing object from the
     * database, without actually querying the database:
     *
     * {{{
     * $post = Posts::create(array('id' => $id, 'moreData' => 'foo'), array('exists' => true));
     * $post->title = 'New title';
     * $success = $post->save();
     * }}}
     *
     * This will create an update query against the object with an ID matching `$id`. Also note that
     * only the `title` field will be updated.
     *
     * @param array $data Any data that this object should be populated with initially.
     * @param array $options Options to be passed to item.
     * @return object Returns a new, _un-saved_ record or document object. In addition to the values
     *         passed to `$data`, the object will also contain any values assigned to the
     *         `'default'` key of each field defined in `$_schema`.
     * @filter
     */
    public static function create(array $data = array(), array $options = array()) {
        $defaults = array('defaults' => true, 'class' => 'entity');
        $options += $defaults;
        return static::_filter(__FUNCTION__, compact('data', 'options'), function($self, $params) {
            $class = $params['options']['class'];
            unset($params['options']['class']);
            if ($class === 'entity' && $params['options']['defaults']) {
                $data = Set::merge(Set::expand($self::schema()->defaults()), $params['data']);
            } else {
                $data = $params['data'];
            }
            $options = array('model' => $self, 'data' => $data) + $params['options'];
            return $self::invokeMethod('_instance', array($class, $options));
        });
    }


    public static function find($type, $options = []) {
    }

    /**
     * Update multiple records or documents with the given data, restricted by the given set of
     * criteria (optional).
     *
     * @param mixed $data Typically an array of key/value pairs that specify the new data with which
     *              the records will be updated. For SQL databases, this can optionally be an SQL
     *              fragment representing the `SET` clause of an `UPDATE` query.
     * @param mixed $conditions An array of key/value pairs representing the scope of the records
     *              to be updated.
     * @param array $options Any database-specific options to use when performing the operation. See
     *              the `delete()` method of the corresponding backend database for available
     *              options.
     * @return boolean Returns `true` if the update operation succeeded, otherwise `false`.
     * @filter
     */
    public static function update($data, $conditions = array(), array $options = array()) {
        $params = compact('data', 'conditions', 'options');

        return static::_filter(__FUNCTION__, $params, function($self, $params) {
            $options = $params + $params['options'] + array('model' => $self, 'type' => 'update');
            unset($options['options']);

            $query = $self::invokeMethod('_instance', array('query', $options));
            return $self::connection()->update($query, $options);
        });
    }

    /**
     * Remove multiple documents or records based on a given set of criteria. **WARNING**: If no
     * criteria are specified, or if the criteria (`$conditions`) is an empty value (i.e. an empty
     * array or `null`), all the data in the backend data source (i.e. table or collection) _will_
     * be deleted.
     *
     * @param mixed $conditions An array of key/value pairs representing the scope of the records or
     *              documents to be deleted.
     * @param array $options Any database-specific options to use when performing the operation. See
     *              the `delete()` method of the corresponding backend database for available
     *              options.
     * @return boolean Returns `true` if the remove operation succeeded, otherwise `false`.
     * @filter
     */
    public static function remove($conditions = array(), array $options = array()) {
        $params = compact('conditions', 'options');

        return static::_filter(__FUNCTION__, $params, function($self, $params) {
            $options = $params['options'] + $params + array('model' => $self, 'type' => 'delete');
            unset($options['options']);

            $query = $self::invokeMethod('_instance', array('query', $options));
            return $self::connection()->delete($query, $options);
        });
    }

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
     * Lazy-initialize the schema for this Model object, if it is not already manually set in the
     * object. You can declare `protected $_schema = array(...)` to define the schema manually.
     *
     * @param mixed $field Optional. You may pass a field name to get schema information for just
     *        one field. Otherwise, an array containing all fields is returned. If `false`, the
     *        schema is reset to an empty value. If an array, field definitions contained are
     *        appended to the schema.
     * @return array
     */
    public static function schema($field = null) {
        $self = static::_object();

        if (!is_object($self->_schema)) {
            $self->_schema = static::connection()->describe(
                $self::meta('source'), $self->_schema, $self->_meta
            );
            if (!is_object($self->_schema)) {
                $class = get_called_class();
                throw new ConfigException("Could not load schema object for model `{$class}`.");
            }
            $key = (array) $self::meta('key');
            if ($self->_schema && $self->_schema->fields() && !$self->_schema->has($key)) {
                $key = implode('`, `', $key);
                throw new ConfigException("Missing key `{$key}` from schema.");
            }
        }
        if ($field === false) {
            return $self->_schema->reset();
        }
        if (is_array($field)) {
            return $self->_schema->append($field);
        }
        return $field ? $self->_schema->fields($field) : $self->_schema;
    }

    protected static function _object() {
        $class = get_called_class();

        if (!isset(static::$_instances[$class])) {
            static::$_instances[$class] = new $class();
            static::config();
        }
        $object = static::_initialize($class);
        return $object;
    }

    /**
     * Reseting the model
     */
    public static function reset() {
        $class = get_called_class();
        unset(static::$_instances[$class]);
    }

    /**
     * An instance method (called on record and document objects) to create or update the record or
     * document in the database that corresponds to `$entity`.
     *
     * For example, to create a new record or document:
     * {{{
     * $post = Posts::create(); // Creates a new object, which doesn't exist in the database yet
     * $post->title = "My post";
     * $success = $post->save();
     * }}}
     *
     * It is also used to update existing database objects, as in the following:
     * {{{
     * $post = Posts::first($id);
     * $post->title = "Revised title";
     * $success = $post->save();
     * }}}
     *
     * By default, an object's data will be checked against the validation rules of the model it is
     * bound to. Any validation errors that result can then be accessed through the `errors()`
     * method.
     *
     * {{{
     * if (!$post->save($someData)) {
     *     return array('errors' => $post->errors());
     * }
     * }}}
     *
     * To override the validation checks and save anyway, you can pass the `'validate'` option:
     *
     * {{{
     * $post->title = "We Don't Need No Stinkin' Validation";
     * $post->body = "I know what I'm doing.";
     * $post->save(null, array('validate' => false));
     * }}}
     *
     * @see lithium\data\Model::$validates
     * @see lithium\data\Model::validates()
     * @see lithium\data\Entity::errors()
     * @param object $entity The record or document object to be saved in the database. This
     *        parameter is implicit and should not be passed under normal circumstances.
     *        In the above example, the call to `save()` on the `$post` object is
     *        transparently proxied through to the `Posts` model class, and `$post` is passed
     *        in as the `$entity` parameter.
     * @param array $data Any data that should be assigned to the record before it is saved.
     * @param array $options Options:
     *        - `'callbacks'` _boolean_: If `false`, all callbacks will be disabled before
     *           executing. Defaults to `true`.
     *        - `'validate'` _mixed_: If `false`, validation will be skipped, and the record will
     *          be immediately saved. Defaults to `true`. May also be specified as an array, in
     *          which case it will replace the default validation rules specified in the
     *         `$validates` property of the model.
     *        - `'events'` _mixed_: A string or array defining one or more validation _events_.
     *          Events are different contexts in which data events can occur, and correspond to the
     *          optional `'on'` key in validation rules. They will be passed to the validates()
     *          method if `'validate'` is not `false`.
     *        - `'whitelist'` _array_: An array of fields that are allowed to be saved to this
     *          record.
     * @return boolean Returns `true` on a successful save operation, `false` on failure.
     * @filter
     */
    public function save($entity, $data = null, array $options = array()) {
        $self = static::_object();
        $_meta = array('model' => get_called_class()) + $self->_meta;
        $_schema = $self->schema();

        $defaults = array(
            'validate' => true,
            'events' => $entity->exists() ? 'update' : 'create',
            'whitelist' => null,
            'callbacks' => true,
            'locked' => $self->_meta['locked'],
            'with' => false
        );
        $options += $defaults;

        $params = compact('entity', 'options');

        if ($data) {
            $entity->set($data);
        }

        if (!$this->_saveRelations($entity, 'belongsTo', $options)) {
            return false;
        }

        $filter = function($self, $params) use ($_meta, $_schema) {
            $entity = $params['entity'];
            $options = $params['options'];

            if ($rules = $options['validate']) {
                $events = $options['events'];
                $validateOpts = is_array($rules) ? compact('rules','events') : compact('events');
                $with = $options['with'];
                if (!$entity->validates(compact('with') + $validateOpts)) {
                    return false;
                }
            }
            if (($whitelist = $options['whitelist']) || $options['locked']) {
                $whitelist = $whitelist ?: array_keys($_schema->fields());
            }

            $type = $entity->exists() ? 'update' : 'create';
            $queryOpts = compact('type', 'whitelist', 'entity') + $options + $_meta;
            $query = $self::invokeMethod('_instance', array('query', $queryOpts));
            $result = $self::connection()->{$type}($query, $options);

            return $result;
        };

        if (!$options['callbacks']) {
            return $filter(get_called_class(), $params);
        }
        $return = static::_filter(__FUNCTION__, $params, $filter);

        $hasRelations = array('hasAndBelongsToMany', 'hasMany', 'hasOne');

        if (!$this->_saveRelations($entity, $hasRelations, $options)) {
            return false;
        }

        return $return;
    }

    /**
     * Save relations helper.
     *
     * @param object $entity The record or document object to be saved in the database.
     * @param array $types Type of relations to save.
     */
    protected function _saveRelations($entity, $types, array $options = array()) {
        $defaults = array('with' => false);
        $options += $defaults;

        if (!$with = static::_withRelations($options['with'])) {
            return true;
        }

        $model = $entity->model();
        $types = (array) $types;
        foreach ($types as $type) {
            foreach ($with as $relName => $value) {
                if (!($rel = $model::relations($relName)) || $rel->type() !== $type) {
                    continue;
                }
                if (!$rel->save($entity, array('with' => $value) + $options)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Deletes the data associated with the current `Model`.
     *
     * @param object $entity Entity to delete.
     * @param array $options Options.
     * @return boolean Success.
     * @filter
     */
    public function delete($entity, array $options = array()) {
        $params = compact('entity', 'options');

        return static::_filter(__FUNCTION__, $params, function($self, $params) {
            $options = $params + $params['options'] + array('model' => $self, 'type' => 'delete');
            unset($options['options']);

            $query = $self::invokeMethod('_instance', array('query', $options));
            return $self::connection()->delete($query, $options);
        });
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
