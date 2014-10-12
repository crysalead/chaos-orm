<?php
namespace chaos\model;

use Iterator;
use set\Set;
use chaos\SourceException;

class Conventions
{
    /**
     * An array of naming convention rules
     *
     * @var array
     */
    protected static $_conventions = null;

    /**
     * Configures the schema class.
     *
     * @param array $config Possible options are:
     *                      - `'conventions'` _array_: Allow to override the default convention rules for generating
     *                                                 primary or foreign key as well as for table/collection names
     *                                                 from an entity class name.
     */
    public static function config($config = [])
    {
        $defaults = [
            'conventions' => [
                'source' => function($class) {
                    $basename = $substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename));
                },
                'primaryKey' => function($class) {
                    return 'id';
                },
                'foreignKey' => function($class) {
                    $basename = $substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename)). '_id';
                },
                'fieldName' => function($class) {
                    $basename = $substr(strrchr($class, '\\'), 1);
                    return Inflector::underscore(Inflector::singularize($basename));
                }
            ]
        ];
        $config = Set::merge($defaults, $config);
        static::$_conventions = $config['conventions'];
    }

    /**
     * Get a specific or all convention rules.
     *
     * @param  string    $name A convention rule or `null` to get all.
     * @return Closure
     * @throws Exception       Throws a `chaos\SourceException` if no rule has been found.
     */
    public static function get($name = null)
    {
        if (!isset(static::$_conventions)) {
            static::config();
        }
        if (!$name) {
            return static::$_conventions;
        }
        if (!isset(static::$_conventions[$name])) {
            throw new SourceException("Error, convention for `'{$name}'` doesn't exists.");
        }
        return static::$_conventions[$name];
    }

    /**
     * Reset the class.
     */
    public static function reset($init = true)
    {
        static::$_conventions = null;
        if ($init) {
            static::config();
        }
    }
}
