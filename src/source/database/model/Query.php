<?php
namespace chaos\source\database\model;

use IteratorAggregate;
use set\Set;
use chaos\SourceException;

/**
 * The Select Query.
 */
class Query implements IteratorAggregate
{
    /**
     * The type.
     *
     * @var string
     */
    protected $_type = null;

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
     * Map beetween generated aliases and corresponding entities.
     *
     * @var array
     */
    protected $_entities = [];

    /**
     * Array containing mappings of relationship and field names, which allow database results to
     * be mapped to the correct objects.
     *
     * @var array
     */
    protected $_map = [];

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
    protected $_query = [
        'conditions' => [],
        'fields'     => [],
        'order'      => [],
        'limit'      => null,
        'page'       => null,
        'with'       => []
    ];

    /**
     * Creates a new record object with default values.
     *
     * @param array $config Possible options are:
     *                      - `'data'`   _array_  : The entiy class.
     *                      - `'parent'` _object_ : The parent instance.
     *                      - `'exists'` _boolean_: The class dependencies.
     *
     */
    public function __construct($config = [])
    {
        $defaults = [
            'type' => 'all',
            'query' => [],
        ];
        $config = Set::merge($defaults, $config);
        $this->_type = $config['type'];
        $this->_entity = $config['entity'];
        $this->_fieldName = $config['fieldName'];
    }

    public function getIterator()
    {
        return $this->get();
    }

    public function get()
    {
        return $this->{$this->_type}();
    }

    public function all()
    {
        $with = $this->__query['with'];
        if (is_array($with)) {
            $with = Set::normalize($with);
        }
    }

    public function first()
    {
    }

    public function count()
    {
    }

    /**
     * Get or Set a unique alias for the query or a query's relation if `$relpath` is set.
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
            $this->_entities[$alias] = $source($class);
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
        $schema = $this->schema()->fields();
        $alias = $this->_alias();


        $fields = $this->_statement->data('fields');
        $this->_map = $this->_map($fields);
    }

    /**
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

    protected function _joinStrategy($alias, $with)
    {
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
