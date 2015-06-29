<?php
namespace chaos\source\database;

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
        'collector' => 'chaos\model\Collector'
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
     * Count the number of identical aliases in a query for building unique aliases.
     *
     * @var array
     */
    protected $_aliasCounter = [];

    /**
     * Map beetween relation pathsand corresponding aliases.
     *
     * @var array
     */
    protected $_aliases = [];

    /**
     * Map beetween generated aliases and corresponding schema.
     *
     * @var array
     */
    protected $_schemas = [];

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

        $schema = $model::schema();
        $source = $schema->source();
        $this->_statement = $this->connection()->dialect()->statement('select');
        $this->_statement->from([$source => $this->_alias('', $schema)]);
        if (isset($config['conditions'])) {
            $this->_statement->where($config['conditions']);
        }
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
     * @param  array  $options The fetching options.
     * @return object          An iterator instance.
     */
    public function get($options = [])
    {
        $defaults = [
            'return' => 'record',
            'fetch'  => PDO::FETCH_ASSOC
        ];
        $options += $defaults;

        $class = $this->_classes['collector'];
        $options['collector'] = isset($options['collector']) ? $options['collector'] : new $class();

        $this->_applyHas();

        $model = $this->_model;

        $collection = [];
        $return = $options['return'];
        $cursor = $this->_statement->execute([
            'fetch' => $return === 'object' ? PDO::FETCH_OBJ : $options['fetch']
        ]);

        switch ($return) {
            case 'record':
                foreach ($cursor as $key => $record) {
                    $collection[] = $model::create($record, [
                        'defaults' => false
                    ]);
                }
                $collection = $model::create($collection, ['type' => 'set']);
            break;
            case 'array':
            case 'object':
                foreach ($cursor as $key => $record) {
                    $collection[] = $record;
                }
            break;
            default:
                throw new SourceException("Invalid value `'{$options['return']}'` as `'return'` option.");
            break;
        }
        $collection = $this->_collect($collection, $options);
        $model::schema()->embed($collection, $this->_with, $options);
        return $collection;
    }

    /**
     * Collects data to avoid entities duplication.
     *
     * @param  mixed  $collection A collection of entities.
     * @return mixed              A collection of entities where duplicates has been replaced by collected one.
     */
    protected function _collect($collection, $options)
    {
        if (!$collector = $options['collector']) {
            return $collection;
        }
        $model = $this->_model;
        $schema = $model::schema();
        $primaryKey = $schema->primaryKey();
        $source = $schema->source();

        foreach ($collection as $index => $item) {
            $id = is_object($item) ? $item->{$primaryKey} : $item[$primaryKey];
            if ($collector->has($source, $id)) {
                $collection[$index] = $collector->get($source, $id);
            } else {
                $collector->set($source, $id, $item);
            }
        }
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
     * Adds some where conditions to the query
     *
     * @param  string|array $conditions The conditions for this query.
     * @return object                   Returns `$this`.
     */
    public function where($conditions)
    {
        $conditions = $this->_statement->dialect()->prefix($conditions, $this->_alias());
        $this->_statement->where($conditions);
        return $this;
    }

    /**
     * Alias for `where()`.
     *
     * @param  string|array $conditions The conditions for this query.
     * @return object                   Returns `$this`.
     */
    public function conditions($conditions)
    {
        return $this->where($conditions);
    }

    /**
     * Adds some group by fields to the query
     *
     * @param  string|array $fields The fields.
     * @return object               Returns `$this`.
     */
    public function group($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $fields = $this->_statement->dialect()->prefix($fields, $this->_alias());
        $this->_statement->group($fields);
        return $this;
    }

    /**
     * Adds some having conditions to the query
     *
     * @param  string|array $conditions The conditions for this query.
     * @return object                   Returns `$this`.
     */
    public function having($conditions)
    {
        $conditions = $this->_statement->dialect()->prefix($conditions, $this->_alias());
        $this->_statement->having($conditions);
        return $this;
    }

    /**
     * Adds some order by fields to the query
     *
     * @param  string|array $fields The fields.
     * @return object               Returns `$this`.
     */
    public function order($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $fields = $this->_statement->dialect()->prefix($fields, $this->_alias());
        $this->_statement->order($fields);
        return $this;
    }

    /**
     * Sets the relations to retrieve.
     *
     * @param  array  $with The relations to load with the query.
     * @return object       Returns `$this`.
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
     * @param  string $path   A dotted relation name or for identifying the query's relation.
     * @param  object $schema The corresponding schema to alias.
     * @return string         A string alias.
     */
    public function _alias($path = '', $schema = null)
    {
        if (func_num_args() < 2) {
            if (isset($this->_aliases[$path])) {
                return $this->_aliases[$path];
            } else {
                throw new SourceException("No alias has been defined for `'{$path}'`", 1);
            }
        }

        $alias = $schema->source();
        if (!isset($this->_aliasCounter[$alias])) {
            $this->_aliasCounter[$alias] = 0;
            $this->_aliases[$path] = $alias;
        } else {
            $alias = $this->_aliases[$path] = $alias . '__' . $this->_aliasCounter[$alias]++;
        }
        $this->_schemas[$alias] = $schema;
        return $alias;
    }

    protected function _applyHas()
    {
        $tree = Set::expand(array_fill_keys(array_keys($this->has()), false));
        $this->_applyJoins($this->_model, $tree, '', $this->_alias());
        foreach ($this->has() as $path => $conditions) {
            $this->where($conditions, $this->_alias($path));
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

        $model = $rel->to();
        $schema = $model::schema();
        $source = $schema->source();
        $toAlias = $this->_alias($path, $schema);

        $this->join(
            [$source => $toAlias],
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
        return ['=' => [[':name' =>"{$fromAlias}.{$fromField}"], [':name' => "{$toAlias}.{$toField}"]]];
    }
}
