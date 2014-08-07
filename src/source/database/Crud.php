<?php
namespace chaos\source\database;

use set\Set;
use chaos\SourceException;

/**
 * The `Crud` trait implements all methods to perform CRUD database operations.
 */
trait Crud {

    /**
     * Array containing all information necessary to perform a query and hydrate result.
     *
     * - `'query'`   _object_ : The current select statement.
     * - `'map'`     _array_  : Array containing mappings of relationship and field names, which
     *                          allow database results to be mapped to the correct objects.
     * - `'aliases'` _array_  : Array of counter representing the number of identical models in a
     *                          query for building results to be mapped to the correct objects.
     * - `'paths'`   _array_  : Map beetween generated aliases and corresponding relation paths.
     * - `'models'`  _array_  : Map beetween generated aliases and corresponding models.
     *
     * @var array
     */
    protected $_traitQuery = [
        'query'   => null,
        'with'    => [],
        'map'     => [],
        'aliases' => [],
        'paths'   => [],
        'models'  => []
    ];

    public static function find($type, $options = []) {
        $this->_traitQuery['query'] = static::connection()->sql()->statement('select');
    }

    public function all()
    {
        $with = $this->_traitQuery['with'];
        if (is_array($with)) {
            $with = Set::normalize($with);
        }
    }

    public function first()
    {
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
     * @param  array  $data    Any data that this object should be populated with initially.
     * @param  array  $options Options to be passed to item.
     * @return object          Returns a new, _un-saved_ record or document object. In addition to
     *                         the values passed to `$data`, the object will also contain any values
     *                         assigned to the `'default'` key of each field defined in `$_schema`.
     */
    public static function create($data = [], $options = []) {
        $defaults = ['class' => 'entity', 'exists' => false];
        $options += $defaults;
        $options['defaults'] = !$options['exists'];

        $class = $options['class'];

        if ($class === 'entity' && $options['defaults']) {
            $data = Set::merge(Set::expand(static::schema()->defaults()), $params['data']);
        } else {
            $data = $params['data'];
        }
        $options = ['model' => get_called_class(), 'data' => $data] + $params['options'];
        $model = $options['model'];
        return new $model($options);
    }

    /**
     * Update multiple records or documents with the given data, restricted by the given set of
     * criteria (optional).
     *
     * @param  mixed $data       Typically an array of key/value pairs that specify the new data with which
     *                           the records will be updated. For SQL databases, this can optionally be
     *                           an SQL fragment representing the `SET` clause of an `UPDATE` query.
     * @param  mixed $conditions An array of key/value pairs representing the scope of the records
     *                           to be updated.
     * @param  array $options    Any database-specific options to use when performing the operation. See
     *                           the `delete()` method of the corresponding backend database for
     *                           available options.
     * @return boolean           Returns `true` if the update operation succeeded, otherwise `false`.
     */
    public static function update($data, $conditions = [], $options = []) {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        $statement = $this->connection()->sql()->statement('update');
        return static::connection()->execute((string) $statement);
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
    public static function remove($conditions = [], $options = []) {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        $statement = $this->connection()->sql()->statement('delete');
        return static::connection()->execute((string) $statement);
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
     * @param array $data    Any data that should be assigned to the record before it is saved.
     * @param array $options Options:
     *                       - `'whitelist'` _array_:   An array of fields that are allowed to
     *                          be saved to this record.
     *                       - `'locked'`    _boolean_: Lock data to the schema fields.
     *                       - `'with'`      _boolean_: List of relations to save.
     * @return boolean       Returns `true` on a successful save operation, `false` on failure.
     */
    public function save($options = []) {
        $defaults = [
            'whitelist' => null,
            'locked' => $this->_meta['locked'],
            'with' => false
        ];
        $options += $defaults;

        if (!$this->_saveRelations($entity, 'belongsTo', $options)) {
            return false;
        }

        if (($whitelist = $options['whitelist']) || $options['locked']) {
            $whitelist = $whitelist ?: array_keys($this->schema()->fields());
        }

        if ($entity->exists()) {
            $statement = $this->connection()->sql()->statement('update');
        } else {
            $statement = $this->connection()->sql()->statement('insert');
        }
        $result = static::connection()->execute((string) $statement);

        $hasRelations = array('hasAndBelongsToMany', 'hasMany', 'hasOne');

        if (!$this->_saveRelations($entity, $hasRelations, $options)) {
            return false;
        }

        return $result;
    }

    /**
     * Save relations helper.
     *
     * @param array $types Type of relations to save.
     */
    protected function _saveRelations($types, $options = []) {
        $defaults = ['with' => false];
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
                if (!$rel->save($entity, ['with' => $value] + $options)) {
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
    public function delete($options = []) {
        $defaults = ['model' => get_called_class()];
        $options += $defaults;

        //Add entity conditions

        $statement = $this->connection()->sql()->statement('delete');
        $result = static::connection()->execute((string) $statement);
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

    /**
     * Redirect call to statement.
     *
     * @param  string $method Query part.
     * @param  array  $params Query parameters.
     * @return object         Returns $this.
     */
    // public function __call($method, $params = []) {
    //     if ($params) {
    //         $this->_statement->$method($params);
    //         return $this;
    //     }
    //     return $this;
    // }

    /**
     * Get or Set a unique alias for the query or a query's relation if `$relpath` is set.
     *
     * @param mixed $alias The value of the alias to set for the passed `$relpath`. For getting an
     *        alias value set alias to `true`.
     * @param string $relpath A dotted relation name or `null` for identifying the query's model.
     * @return string An alias value or `null` for an unexisting `$relpath` alias.
     */
    public function _alias($alias = true, $relpath = null) {
        if ($alias === true) {
            if (!$relpath) {
                return $this->_alias;
            }
            $return = array_search($relpath, $this->_paths);
            return $return ?: null;
        }

        if ($relpath === null) {
            $this->_alias = $alias;
        }

        if ($relpath === null) {
            $this->_models[$alias] = $this->_model;
        }

        $relpath = (string) $relpath;
        unset($this->_paths[array_search($relpath, $this->_paths)]);

        if (!$alias && $relpath) {
            $last = strrpos($relpath, '.');
            $alias = $last ? substr($relpath, $last + 1) : $relpath;
        }

        if (isset($this->_aliases[$alias])) {
            $this->_aliases[$alias]++;
            $alias .= '__' . $this->_aliases[$alias];
        } else {
            $this->_aliases[$alias] = 1;
        }

        $this->_paths[$alias] = $relpath;
        return $alias;
    }

    public function applyStrategy() {
        $schema = $this->schema()->fields();
        $alias = $this->_alias();


        $fields = $this->_statement->data('fields');
        $this->_map = $this->_map($context, $fields);
    }

    /**
     *
     * @param array $fields Array of formatted fields.
     */
    protected function _map($fields = []) {
        $model = $this->_model;
        $paths = $this->_paths;
        $result = [];

        if (!$fields) {
            foreach ($paths as $alias => $relation) {
                $model = $models[$alias];
                $result[$relation] = $model::schema()->names();
            }
            return $result;
        }

        $unalias = function ($value) {
            if (is_object($value) && isset($value->scalar)) {
                $value = $value->scalar;
            }
            $aliasing = preg_split("/\s+as\s+/i", $value);
            return isset($aliasing[1]) ? $aliasing[1] : $value;
        };

        if (isset($fields[0])) {
            $raw = array_map($unalias, $fields[0]);
            unset($fields[0]);
        }

        $models = $this->_models;
        $alias = $this->_alias;
        $fields = isset($fields[$alias]) ? [$alias => $fields[$alias]] + $fields : $fields;

        foreach ($fields as $field => $value) {
            if (is_array($value)) {
                if (isset($value['*'])) {
                    $relModel = $models[$field];
                    $result[$paths[$field]] = $relModel::schema()->names();
                } else {
                    $result[$paths[$field]] = array_map($unalias, array_keys($value));
                }
            }
        }

        if (isset($raw)) {
            $result[''] = isset($result['']) ? array_merge($raw, $result['']) : $raw;
        }
        return $result;
    }

    protected function _joinStrategy($alias, $with) {
        $tree = Set::expand(array_fill_keys(array_keys($with), false));
        $deps = [$alias => []];

        $this->_join($model, $tree, '', $alias, $deps);

        $models = $this->_models;
        foreach ($this->_fields() as $field) {
            if (!is_string($field)) {
                continue;
            }
            list($alias, $field) = $self->invokeMethod('_splitFieldname', array($field));
            $alias = $alias ?: $field;
            if ($alias && isset($models[$alias])) {
                foreach ($deps[$alias] as $depAlias) {
                    $depModel = $models[$depAlias];
                    $this->_fields(array($depAlias => (array) $depModel::meta('key')));
                }
            }
        }
    }

    protected function _join($model, $tree, $path, $from, &$deps) {
        foreach ($tree as $name => $childs) {
            if (!$rel = $model::relations($name)) {
                throw new SourceException("Model relationship `{$name}` not found.");
            }

            $constraints = [];
            $alias = $name;
            $relPath = $path ? $path . '.' . $name : $name;
            if (isset($with[$relPath])) {
                list($unallowed, $allowed) = Set::slice($with[$relPath], ['alias', 'constraints']);
                if ($unallowed) {
                    throw new SourceException("Only `'alias'` and `'constraints'` are allowed.");
                }
                extract($with[$relPath]);
            }

            if ($rel->type() !== 'hasMany') {
                $this->_joinClassic($rel, $from, $this->_alias($alias, $path), $constraints, $relPath, $deps);
            } else {
                $this->_joinHabtm($rel, $from, $to, $constraints, $relPath, $deps);
            }

            if (!empty($childs)) {
                $this->_join($rel->to(), $childs, $relPath, $to, $deps);
            }
        }
    }

    protected function _joinClassic($rel, $from, $to, $constraints, $path, &$deps) {
        $deps[$to] = $deps[$from];
        $deps[$to][] = $from;

        if ($this->_relationships($path) === null) {
            $this->_relationships($path, array(
                'type' => $rel->type(),
                'model' => $rel->to(),
                'fieldName' => $rel->fieldName(),
                'alias' => $to
            ));
            $this->_join($rel, $from, $to, $constraints);
        }
    }

    protected function _joinHabtm($rel, $from, $to, $constraints, $path, &$deps) {
        $nameVia = $rel->data('via');
        $relnameVia = $path ? $path . '.' . $nameVia : $nameVia;

        if (!$relVia = $model::relations($nameVia)) {
            $message = "Model relationship `{$nameVia}` not found.";
            throw new SourceException($message);
        }

        if (!$config = $this->_relationships($relnameVia)) {
            $aliasVia = $this->_alias($nameVia, $relnameVia);
            $this->_relationships($relnameVia, array(
                'type' => $relVia->type(),
                'model' => $relVia->to(),
                'fieldName' => $relVia->fieldName(),
                'alias' => $aliasVia
            ));
            $this->_join($relVia, $from, $aliasVia, $self->on($rel));
        } else {
            $aliasVia = $config['alias'];
        }

        $deps[$aliasVia] = $deps[$from];
        $deps[$aliasVia][] = $from;

        if (!$this->_relationships($relPath)) {
            $to = $this->_alias($alias, $relPath);
            $modelVia = $relVia->data('to');
            if (!$relTo = $modelVia::relations($name)) {
                $message = "Model relationship `{$name}` ";
                $message .= "via `{$nameVia}` not found.";
                throw new SourceException($message);
            }
            $this->_relationships($relPath, array(
                'type' => $rel->type(),
                'model' => $relTo->to(),
                'fieldName' => $rel->fieldName(),
                'alias' => $to
            ));
             $this->_join($relTo, $aliasVia, $to, $constraints);
        }

        $deps[$to] = $deps[$aliasVia];
        $deps[$to][] = $aliasVia;
    }

}

?>