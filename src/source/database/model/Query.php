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
     * The finder statement instance.
     *
     * @var string
     */
    protected $_statement = null;

    /**
     * Count the number of identical models in a query for building
     * unique aliases
     *
     * @var array
     */
    protected $_aliases = 0;

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
     * Map beetween generated aliases and corresponding models.
     *
     * @var array
     */
    protected $_relationships = [];

    /**
     * The relations to include.
     *
     * @var array
     */
    protected $_with = [];

    /**
     * Some conditions over some relations.
     *
     * @var array
     */
    protected $_has = [];

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
        $source = $model::schema()->source();
        $this->_statement = $this->connection()->sql()->statement('select');
        $this->_statement->from([$source => $this->_alias()]);
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
        if (method_exists($this->_statement, $name)) {
            call_user_func_array([$this->_statement, $name], $params);
            return $this;
        }
        array_unshift($params, $this);
        return call_user_func_array([$this->_model, $name], $params);
    }

    public function statement()
    {
        return $this->_statement;
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
        $this->_applyHas();
        $cursor = $this->connection()->query($this->_statement->toString($this->_paths));  //TODO pass connection to statement and run it directly
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
        $this->_statement->fields([':plain' => 'COUNT(*)']);
        $cursor = $this->connection()->query($this->_statement->toString());
        $result = $cursor->current();
        return (int) current($result);
    }

    /**
     * Sets the relations to retrieve.
     *
     * @param array The relations to load with the query.
     */
    public function with($with = null)
    {
        if (!$with) {
            return $this->_with;
        }
        if (!is_array($with)) {
            $with = func_get_args();
        }
        $with = Set::normalize($with);
        $this->_has = Set::merge($this->_has, array_filter($with));
        $this->_with = Set::merge($this->_with, Set::expand(array_fill_keys(array_keys($with), [])));
        return $this;
    }

    /**
     * Sets the conditionnal dependency over some relations.
     *
     * @param array The conditionnal dependency.
     */
    public function has($has = null, $conditions = [])
    {
        if (!$has) {
            return $this->_has;
        }
        if (!is_array($has)) {
            $has = [$has => $conditions];
        }
        $this->_has = array_merge($this->_has, $has);
        return $this;
    }

    /**
     * Gets a unique alias for the query or a query's relation if `$relpath` is set.
     *
     * @param  mixed  $alias   The value of the alias to set for the passed `$relpath`. For getting an
     *                         alias value set alias to `true`.
     * @param  string $relpath A dotted relation name or `null` for identifying the query's model.
     * @return string          An alias value or `null` for an unexisting `$relpath` alias.
     */
    public function _alias($relpath = '')
    {
        if (isset($this->_paths[$relpath])) {
            return $this->_paths[$relpath];
        }

        $alias = "t" . $this->_aliases;
        $this->_paths[$relpath] = $alias;

        $this->_aliases++;
        return $alias;
    }

    protected function _applyHas()
    {
        $tree = Set::expand(array_fill_keys(array_keys($this->_with), false));
        $alias = $this->_alias();
        $deps = [$alias => []];

        //$this->_applyJoin($this->_model, $tree, '', $alias, $deps);
    }

    protected function _applyJoin($model, $tree, $path, $from, &$deps)
    {
        foreach ($tree as $name => $childs) {
            if (!$rel = $model::relation($name)) {
                throw new SourceException("Model relationship `{$name}` not found.");
            }

            $alias = $name;
            $relPath = $path ? $path . '.' . $name : $name;

            $to = $this->_alias($relPath);

            if ($rel->type() !== 'hasAndBelongsToMany') {
                $this->_joinClassic($rel, $from, $to, $relPath, $deps);
            } else {
                $this->_joinHabtm($rel, $from, $to, $relPath, $deps);
            }

            if (!empty($childs)) {
                $this->_applyJoin($rel->to(), $childs, $relPath, $to, $deps);
            }
        }
    }

    protected function _joinClassic($rel, $from, $to, $path, &$deps)
    {
        $deps[$to] = $deps[$from];
        $deps[$to][] = $from;

        if ($this->relationships($path) === null) {
            $this->relationships($path, array(
                'type' => $rel->type(),
                'model' => $rel->to(),
                'fieldName' => $rel->fieldName(),
                'alias' => $to
            ));
            $this->_join($rel, $from, $to);
        }
    }

    protected function _joinHabtm($rel, $from, $to, $path, &$deps)
    {
        $nameVia = $rel->data('via');
        $relnameVia = $path ? $path . '.' . $nameVia : $nameVia;

        if (!$relVia = $model::relations($nameVia)) {
            $message = "Model relationship `{$nameVia}` not found.";
            throw new SourceException($message);
        }

        if (!$config = $this->relationships($relnameVia)) {
            $aliasVia = $this->_alias($relnameVia);
            $this->relationships($relnameVia, array(
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

        if ($this->relationships($relPath)) {
            return;
        }

        $to = $this->_alias($relPath);
        $modelVia = $relVia->data('to');
        if (!$relTo = $modelVia::relations($name)) {
            $message = "Model relationship `{$name}` ";
            $message .= "via `{$nameVia}` not found.";
            throw new SourceException($message);
        }
        $this->relationships($relPath, array(
            'type' => $rel->type(),
            'model' => $relTo->to(),
            'fieldName' => $rel->fieldName(),
            'alias' => $to
        ));
        $this->_join($relTo, $aliasVia, $to);

        $deps[$to] = $deps[$aliasVia];
        $deps[$to][] = $aliasVia;
    }

    /**
     * Set a query's join according a Relationship.
     *
     * @param object $rel A Relationship instance
     * @param string $fromAlias Set a specific alias for the `'from'` `Model`.
     * @param string $toAlias Set a specific alias for `'to'` `Model`.
     * @param mixed $constraints If `$constraints` is an array, it will be merged to defaults
     *        constraints. If `$constraints` is an object, defaults won't be merged.
     */
    protected function _join($rel, $fromAlias = null, $toAlias = null, $constraints = [])
    {
        $model = $rel->to();
        if ($fromAlias === null) {
            $from = $rel->from();
            $fromAlias = $this->alias();
        }
        if ($toAlias === null) {
            $toAlias = $this->alias($rel->name());
        }
        if (!is_object($constraints)) {
            $constraints = $this->on($rel, $fromAlias, $toAlias, $constraints);
        } else {
            $constraints = (array) $constraints;
        }

        $this->join([$model::schema()->source() => $toAlias], $constraints, 'LEFT');
    }

    /**
     * Build the `ON` constraints from a `Relationship` instance
     *
     * @param object $rel A Relationship instance
     * @param string $fromAlias Set a specific alias for the `'from'` `Model`.
     * @param string $toAlias Set a specific alias for `'to'` `Model`.
     * @param array $constraints Array of additionnal $constraints.
     * @return array A constraints array.
     */
    public function on($rel, $aliasFrom = null, $aliasTo = null, $constraints = [])
    {
        if ($rel->type() === 'hasAndBelongsToMany') {
            return $constraints;
        }
        $model = $rel->from();
        $aliasFrom = $aliasFrom ?: $model::schema()->source();
        $aliasTo = $aliasTo ?: $rel->name();
        $keyConstraints = array();
        $from = $model::schema()->primaryKey();
        $to = $rel->key();
        $keyConstraints = ['=' => [[':name' =>"{$aliasFrom}.{$from}"], [':name' => "{$aliasTo}.{$to}"]]];

        $mapAlias = [$model::schema()->source() => $aliasFrom, $rel->name() => $aliasTo];
        $relConstraints = $this->_on((array) $rel->constraints(), $aliasFrom, $aliasTo, $mapAlias);
        $constraints = $this->_on($constraints, $aliasFrom, $aliasTo, array());
        return $constraints + $relConstraints + $keyConstraints;
    }

    protected function _on(array $constraints, $aliasFrom, $aliasTo, $mapAlias = array())
    {
        $result = array();
        foreach ($constraints as $key => $value) {
            $isAliasable = (
                !is_numeric($key) &&
                !isset($this->_constraintTypes[$key]) &&
                !isset($this->_operators[$key])
            );
            if ($isAliasable) {
                $key = $this->_aliasing($key, $aliasFrom, $mapAlias);
            }
            if (is_string($value)) {
                $result[$key] = $this->_aliasing($value, $aliasTo, $mapAlias);
            } elseif (is_array($value)) {
                $result[$key] = $this->_on($value, $aliasFrom, $aliasTo, $mapAlias);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Sets and gets the relationships.
     *
     * @param  string $relpath A dotted path.
     * @param  array  $config  The config array to set.
     * @return mixed           The relationships array or a relationship array if `$relpath` is set. Returns
     *                         `null` if a join doesn't exist.
     * @throws InvalidArgumentException
     */
    public function relationships($relpath = null, $config = null)
    {
        if ($config) {
            if (!$relpath) {
                throw new InvalidArgumentException("The relation dotted path is empty.");
            }
            if (isset($config['model']) && isset($config['alias'])) {
                $this->_models[$config['alias']] = $config['model'];
            }
            $this->_relationships[$relpath] = $config;
            return $this;
        }
        if (!$relpath) {
            return $this->_relationships;
        }
        if (isset($this->_relationships[$relpath])) {
            return $this->_relationships[$relpath];
        }
    }
}
