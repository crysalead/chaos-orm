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
                    return Inflector::underscore(Inflector::singularize($basename));
                },
                'primaryKey' => function() {
                    return 'id';
                },
                'foreignKey' => function($class) {
                    $basename = substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename)). '_id';
                },
                'fieldName' => function($class) {
                    $basename = substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename));
                }
            ]
        ];
        $config = Set::merge($defaults, $config);
        $this->_conventions = $config['conventions'];
    }

    /**
     * Get a specific or all convention rules.
     *
     * @param  string    $name A convention rule or `null` to get all.
     * @return Closure
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
}
