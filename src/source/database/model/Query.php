<?php
namespace chaos\source\database\model;

use PDO;
use IteratorAggregate;
use set\Set;
use chaos\SourceException;

/**
 * The Query wrapper.
 */
class Query implements IteratorAggregate
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [
        'data-collector' => 'chaos\source\DataCollector'
    ];

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
    public function get($options = [])
    {
        $defaults = [
            'return'    => 'record',
            'fetchMode' => PDO::FETCH_ASSOC
        ];
        $options += $defaults;

        $dataCollector = $this->_classes['data-collector'];
        $collector = isset($options['collector']) ? $options['collector'] : new $dataCollector();

        $this->_applyHas();

        $model = $this->_model;
        $schema = $model::schema();
        $primaryKey = $schema->primaryKey();
        $source = $schema->source();

        $collection = [];
        $return = $options['return'];
        $cursor = $this->_statement->execute([
            'paths'     => $this->_paths,
            'fetchMode' => $return === 'object' ? PDO::FETCH_OBJ : $options['fetchMode']
        ]);

        switch ($return) {
            case 'record':
                foreach ($cursor as $key => $record) {
                    $collector->set($source, $record[$primaryKey], $collection[] = $model::create($record, [
                        'defaults' => false
                    ]));
                }
                $collection = $model::create($collection, ['type' => 'set']);
            break;
            case 'array':
                foreach ($cursor as $key => $record) {
                    $collector->set($source, $record[$primaryKey], $collection[] = $record);
                }
            break;
            case 'object':
                foreach ($cursor as $key => $record) {
                    $collector->set($source, $record->{$primaryKey}, $collection[] = $record);
                }
            break;
            default:
                throw new SourceException("Invalid value `'{$options['return']}'` as `'return'` option.");
            break;
        }
        //$this->embed($this->_with);
        return $collection;
    }

    /**
     * Alias for `get()`
     *
     * @return object An iterator instance.
     */
    public function all($options = [])
    {
        return $this->get($options);
    }

    /**
     * Executes the query and returns the first result only.
     *
     * @return object An entity instance.
     */
    public function first($options = [])
    {
        $result = $this->get($options);
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
        $this->_with = Set::merge($this->_with, $with);
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
        $tree = Set::expand(array_fill_keys(array_keys($this->has()), false));
        $this->_applyJoins($this->_model, $tree, '', $this->_alias());
        foreach ($this->has() as $path => $conditions) {
            $this->where($conditions);
        }
    }

    protected function _applyJoins($model, $tree, $basePath, $aliasFrom)
    {
        foreach ($tree as $name => $childs) {
            $rel = $model::relation($name);
            $path = $basePath ? $basePath . '.' . $name : $name;

            if ($rel->type() !== 'hasManyThrough') {
                $to = $this->_join($path, $rel, $aliasFrom);
            } else {
                $name = $rel->using();
                $nameThrough = $rel->through();
                $pathThrough = $path ? $path . '.' . $nameThrough : $nameThrough;
                $model = $rel->from();

                $relThrough = $model::relation($nameThrough);
                $aliasThrough = $this->_join($pathThrough, $relThrough, $aliasFrom);

                $to = $this->_alias($path);
                $modelThrough = $relThrough->to();
                $relTo = $modelThrough::relation($name);
                $to = $this->_join($path, $relTo, $aliasThrough);
            }

            if (!empty($childs)) {
                $this->_applyJoins($rel->to(), $childs, $path, $to);
            }
        }
    }

    /**
     * Set a query's join according a Relationship.
     *
     * @param  string $path      The relation path.
     * @param  object $rel       A Relationship instance.
     * @param  string $fromAlias The "from" model alias.
     * @return string            The "to" model alias.
     */
    protected function _join($path, $rel, $fromAlias)
    {
        if (isset($this->_aliases[$path])) {
            return $this->_aliases[$path];
        }

        $toAlias = $this->_alias($path);
        $model = $rel->to();

        $this->join(
            [$model::schema()->source() => $toAlias],
            $this->_on($rel, $fromAlias, $toAlias),
            'LEFT'
        );
        return $toAlias;
    }

    /**
     * Build the `ON` constraints from a `Relationship` instance.
     *
     * @param  object $rel       A Relationship instance.
     * @param  string $fromAlias The "from" model alias.
     * @param  string $toAlias   The "to" model alias.
     * @return array             A constraints array.
     */
    protected function _on($rel, $fromAlias, $toAlias)
    {
        if ($rel->type() === 'hasManyThrough') {
            return [];
        }
        $keys = $rel->keys();
        list($fromField, $toField) = each($keys);
        $keyConstraints = ['=' => [[':name' =>"{$fromAlias}.{$fromField}"], [':name' => "{$toAlias}.{$toField}"]]];
        return $keyConstraints;
    }
}
