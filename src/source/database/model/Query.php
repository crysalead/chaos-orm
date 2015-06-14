<?php
namespace chaos\source\database\model;

use IteratorAggregate;
use set\Set;
use chaos\SourceException;

/**
 * The Query wrapper.
 */
class Query implements IteratorAggregate
{
    /**
     * The connection to the datasource.
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * The fully namespaced model class name on which this query is starting.
     *
     * @var string
     */
    protected $_model = null;

    /**
     * The `SELECT` statement instance.
     *
     * @var string
     */
    protected $_select = null;

    /**
     * Count the number of identical models in a query for building
     * unique aliases
     *
     * @var array
     */
    protected $_alias = [];

    /**
     * Map beetween generated aliases and corresponding relation paths
     *
     * @var array
     */
    protected $_paths = [];

    /**
     * Map beetween generated aliases and corresponding models.
     *
     * @var array
     */
    protected $_models = [];

    /**
     * Array containing mappings of relationship and field names, which allow database results to
     * be mapped to the correct objects.
     *
     * @var array
     */
    protected $_map = [];

    /**
     * The relations to load
     */
    protected $_with = [];

    /**
     * Creates a new record object with default values.
     *
     * @param array $config Possible options are:
     *                      - `'type'`       _string_ : The type of query.
     *                      - `'connection'` _object_ : The connection instance.
     *                      - `'model'`      _string_ : The model class.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'connection' => null,
            'model'      => null
        ];
        $config = Set::merge($defaults, $config);
        $model = $this->_model = $config['model'];
        $this->_connection = $config['connection'];
        //$this->_fieldName = $config['fieldName'];
        $this->_alias = 'Model';
        $this->_select = $this->connection()->sql()->statement('select'); //TODO pass this as parameter
        $this->_select->from($model::schema()->source());
    }

    /**
     * When not supported, delegate the call to the sql statement.
     *
     * @param  string $name   The name of the matcher.
     * @param  array  $params The parameters to pass to the matcher.
     * @return object         Returns `$this`.
     */
    public function __call($name, $params = [])
    {
        if (method_exists($this->_model, $scope = 'scope' . ucfirst($name))) {
            array_unshift($params, $this);
            call_user_func_array([$this->_model, $scope], $params);
        } else {
            call_user_func_array([$this->_select, $name], $params);
        }
        return $this;
    }

    /**
     * Gets the connection object to which this query is bound.
     *
     * @return object    Returns a connection instance.
     * @throws Exception Throws a `chaos\SourceException` if a connection isn't set.
     */
    public function connection()
    {
        if (!$this->_connection) {
            throw new SourceException("Error, missing connection for this query.");
        }
        return $this->_connection;
    }

    /**
     * Executes the query and returns the result (must implements the `Iterator` interface).
     *
     * (Automagically called on `foreach`)
     *
     * @return object An iterator instance.
     */
    public function getIterator()
    {
        return $this->get();
    }

    /**
     * Executes the query and returns the result.
     *
     * @return object An iterator instance.
     */
    public function get()
    {
        $query = $this;
        $with = Set::normalize($this->_with);
        $cursor = $this->connection()->query($this->_select->toString());  //TODO pass connection to statement and run it directly
        $model = $this->_model;
        return $model::create([], compact('query', 'cursor') + [
            'type' => 'set', 'defaults' => false
        ]);
    }

    /**
     * Alias for `get()`
     *
     * @return object An iterator instance.
     */
    public function all()
    {
        return $this->get();
    }

    /**
     * Executes the query and returns the first result only.
     *
     * @return object An entity instance.
     */
    public function first()
    {
        $result = $this->get();
        return is_object($result) ? $result->rewind() : $result;
    }

    /**
     * Executes the query and returns the count number.
     *
     * @return integer The number of rows in result.
     */
    public function count()
    {
        $this->_select->fields([':plain' => 'COUNT(*)']);
        $cursor = $this->connection()->query($this->_select->toString());
        $result = $cursor->current();
        return (int) current($result);
    }

    /**
     * Sets the relations to retrieve.
     *
     * @param array The relations to load with the query.
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        $this->_with = $relations;
        return $this;
    }

    /**
     * Gets or Sets a unique alias for the query or a query's relation if `$relpath` is set.
     *
     * @param  mixed  $alias   The value of the alias to set for the passed `$relpath`. For getting an
     *                         alias value set alias to `true`.
     * @param  string $relpath A dotted relation name or `null` for identifying the query's model.
     * @return string          An alias value or `null` for an unexisting `$relpath` alias.
     */
    public function _alias($alias = true, $relpath = null)
    {
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
            $class = is_object($this->_entity) ? get_class($this->_entity) : $this->_entity;
            $source = Conventions::get('source');
            $this->_models[$alias] = $source($class);
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

    public function applyStrategy()
    {
        $options = ['strategy' => 'joined'];
        $model = $context->model();

        $schema = $this->schema()->fields();
        $alias = $this->_alias();


        $fields = $this->_select->data('fields');
        $this->_map = $this->_map($fields);
    }

    /**
     * The query map.
     *
     * @param array $fields Array of formatted fields.
     */
    protected function _map($fields = [])
    {
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

    protected function _joinStrategy()
    {
        $alias = $this->_alias;
        $tree = Set::expand(array_fill_keys(array_keys($this->_with), false));
        $deps = [$alias => []];

        $this->_join($this->_model, $tree, '', $alias, $deps);

        $models = $this->_models;
        $fields = $this->_select->data('fields');
        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }
            list($alias, $field) = $this->connection()->sql()->undot($field);
            $alias = $alias ?: $field;
            if ($alias && isset($models[$alias])) {
                foreach ($deps[$alias] as $depAlias) {
                    $depModel = $models[$depAlias];
                    $this->_select->fields([$depAlias => [$depModel::schema()->primaryKey()]]);
                }
            }
        }
    }

    protected function _join($model, $tree, $path, $from, &$deps)
    {
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

    protected function _joinClassic($rel, $from, $to, $constraints, $path, &$deps)
    {
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

    protected function _joinHabtm($rel, $from, $to, $constraints, $path, &$deps)
    {
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
