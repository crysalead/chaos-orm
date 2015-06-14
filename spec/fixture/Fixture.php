<?php
namespace chaos\spec\fixture;

use Exception;
use set\Set;
use kahlan\plugin\Stub;

class Fixture
{
    /**
     * Class dependencies.
     *
     * @var array
     */
    protected $_classes = [];

    /**
     * The connection to the datasource.
     *
     * @var object
     */
    protected $_connection = null;

    /**
     * The source name.
     *
     * @var string
     */
    protected $_source = null;

    /**
     * The schema definition.
     *
     * @var array
     */
    protected $_schema = [];

    /**
     * The alter definitions.
     *
     * @var array
     */
    protected $_alters = [];

    /**
     * The model.
     *
     * @var string
     */
    protected $_model = null;

    /**
     * Constructor.
     *
     * @param array $config Possible options are:
     *                      - `'classes'`     _array_  : The class dependencies.
     *                      - `'connection'`  _object_ : The connection instance.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'classes'    => [
                'schema' => 'chaos\source\database\Schema',
                'model'  => 'chaos\model\Model'
            ],
            'connection' => null,
            'alters'     => []
        ];

        $config = Set::merge($defaults, $config);

        $this->_classes = $config['classes'];
        $this->_connection = $config['connection'];
        $this->_alters = $config['alters'];

        $this->model($this->_classes['model']);
    }

    /**
     * Gets/sets the connection object to which this schema is bound.
     *
     * @return object    $connection Returns a connection instance.
     * @throws Exception             Throws a `chaos\SourceException` if a connection isn't set.
     */
    public function connection($connection = null)
    {
        if (func_num_args()) {
            return $this->_connection = $connection;
        }
        if (!$this->_connection) {
            throw new SourceException("Error, missing connection for this schema.");
        }
        return $this->_connection;
    }

    /**
     * Returns a dynamically created model based on the model class name passed as parameter.
     *
     * @param  string $model A model class name to extends from or `null` to get the default binded model.
     * @return string        A dynamically created model class name.
     */
    public function model($model = null)
    {
        if (!func_num_args()) {
            return $this->_model;
        }
        $stub = Stub::classname(['extends' => $model]);
        $stub::config([
            'connection' => $this->connection(),
            'schema'     => $this->schema()
        ]);
        return $this->_model = $stub;
    }

    /**
     * Returns the source name of the fixture.
     *
     * @return string
     */
    public function source()
    {
        return $this->_source;
    }

    /**
     * Gets the associated schema.
     *
     * @param  array  $options The schema option.
     * @return object          The associated schema instance.
     */
    public function schema()
    {
        static $cache = null;

        if ($cache) {
            return $cache;
        }
        $schema = $this->_classes['schema'];

        $options = $this->_schema;

        if ($options['fields']) {
            $options['fields'] = $this->_alterFields($options['fields']);
        }

        return $cache = new $schema($options + [
            'connection' => $this->connection(),
            'source'     => $this->source()
        ]);
    }

    /**
     * Populates some records.
     *
     * @return string
     */
    public function populate($records = [])
    {
        $model = $this->model();

        if(count(array_filter(array_keys($records), 'is_string'))) {
            $records = [$records];
        }

        $fields = $this->schema()->fields('type');

        foreach ($records as $record) {
            $data = $this->_alterRecord($record);
            $data = array_intersect_key($data, $fields);
            $entity = $model::create($data);
            $entity->save();
        }
    }

    /**
     * Formats fields according the alter configuration.
     *
     * @param  array $fields An array of fields
     * @return array         Returns the modified fields.
     */
    protected function _alterFields($fields = []) {
        foreach ($this->_alters as $mode => $values) {
            foreach ($values as $key => $value) {
                switch($mode) {
                    case 'add':
                        $fields[$key] = $value;
                        break;
                    case 'change':
                        if (isset($fields[$key]) && isset($value['to'])) {
                            $field = $fields[$key];
                            unset($fields[$key]);
                            $to = $value['to'];
                            unset($value['to']);
                            unset($value['value']);
                            $fields[$to] = $value + $field;
                        }
                        break;
                    case 'drop':
                        unset($fields[$value]);
                        break;
                }
            }
        }
        return $fields;
    }

    /**
     * Formats values according the alter configuration.
     *
     * @param  array $record The record array.
     * @return array         Returns the modified record.
     */
    protected function _alterRecord($record = []) {
        $result = array();
        foreach ($record as $name => $value) {
            if (isset($this->_alters['change'][$name])) {
                $alter = $this->_alters['change'][$name];
                if (isset($alter['value'])) {
                    $function = $alter['value'];
                    $value = $function($record[$name]);
                } else {
                    $value = $record[$name];
                }
                if (isset($alter['to'])) {
                    $result[$alter['to']] = $value;
                } else {
                    $result[$name] = $value;
                }
            } else {
                $result[$name] = $value;
            }
        }
        return $result;
    }
}
