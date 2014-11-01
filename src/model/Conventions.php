<?php
namespace chaos\model;

use Iterator;
use set\Set;
use inflector\Inflector;
use chaos\SourceException;

class Conventions
{
    /**
     * An array of naming convention rules
     *
     * @var array
     */
    protected $_conventions = null;

    /**
     * Configures the schema class.
     *
     * @param array $config Possible options are:
     *                      - `'conventions'` _array_: Allow to override the default convention rules for generating
     *                                                 primary or foreign key as well as for table/collection names
     *                                                 from an entity class name.
     */
    public function config($config = [])
    {
        $defaults = [
            'conventions' => [
                'source' => function($class) {
                    $basename = substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore($basename);
                },
                'primaryKey' => function() {
                    return 'id';
                },
                'foreignKey' => function($class) {
                    $basename = substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename)). '_id';
                }
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_conventions = $config['conventions'];
    }

    /**
     * Adds a specific convention rule.
     *
     * @param  string  $name    The name of the convention or `null` to get all.
     * @param  Closure $closure The convention closure.
     * @return Closure          The passed convention closure.
     */
    public function add($name, $closure)
    {
        return $this->_conventions[$name] = $closure;
    }

    /**
     * Gets a specific or all convention rules.
     *
     * @param  string    $name The name of the convention or `null` to get all.
     * @return mixed           The closure or an array of closures.
     * @throws Exception       Throws a `chaos\SourceException` if no rule has been found.
     */
    public function get($name = null)
    {
        if (!isset($this->_conventions)) {
            $this->config();
        }
        if (!$name) {
            return $this->_conventions;
        }
        if (!isset($this->_conventions[$name])) {
            throw new SourceException("Error, convention for `'{$name}'` doesn't exists.");
        }
        return $this->_conventions[$name];
    }

    /**
     * Applies a specific convention rules.
     *
     * @param  string    $name  The name of the convention.
     * @param  mixed     $param Parameter to pass to the closure.
     * @param  mixed     ...    Parameter to pass to the closure.
     * @return mixed
     * @throws Exception       Throws a `chaos\SourceException` if no rule has been found.
     */
    public function apply($name) {
        $params = func_get_args();
        array_shift($params);
        $convention = $this->get($name);
        return call_user_func_array($convention, $params);
    }

}
