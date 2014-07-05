<?php
namespace chaos\source\database;

use PDO;
use PDOException;
use PDOStatement;
use InvalidArgumentException;
use chaos\SourceException;

/**
 * Base PDO adapter
 */
abstract class Database extends \chaos\Source {

	/**
	 * Quoting identifier character.
	 *
	 * @var array
	 */
	protected $_escape = '"';

	/**
	 * MySQL-specific value denoting whether or not table aliases should be used in DELETE and
	 * UPDATE queries.
	 *
	 * @var boolean
	 */
	protected $_alias = false;

	/**
	 * Creates the database object and set default values for it.
	 *
	 * Options defined:
	 *  - 'database' _string_ Name of the database to use. Defaults to `null`.
	 *  - 'host' _string_ Name/address of server to connect to. Defaults to 'localhost'.
	 *  - 'login' _string_ Username to use when connecting to server. Defaults to 'root'.
	 *  - 'password' _string_ Password to use when connecting to server. Defaults to `''`.
	 *  - 'persistent' _boolean_ If true a persistent connection will be attempted, provided the
	 *    adapter supports it. Defaults to `true`.
	 *
	 * @param  $config array Array of configuration options.
	 * @return Database object.
	 */
	public function __construct($config = []) {
		$defaults = [
			'persistent' => true,
			'host'       => 'localhost',
			'login'      => 'root',
			'password'   => '',
			'database'   => null,
			'encoding'   => null,
			'dsn'        => null,
			'options'    => []
		];
		parent::__construct($config + $defaults);
	}

	/**
	 * Get database connection.
	 *
	 * @return object PDO
	 */
	public function connect() {
		if ($this->_connection) {
			return $this->_connection;
		}
		$config = $this->_config;

		if (!$config['database']) {
			throw new PDOException('No Database configured');
		}
		if (!$config['dsn']) {
			throw new PDOException('No DSN setup for DB Connection');
		}
		$dsn = $config['dsn'];

		$options = $config['options'] + [
			PDO::ATTR_PERSISTENT => $config['persistent'],
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		];

		try {
			$this->_connection = new PDO($dsn, $config['login'], $config['password'], $options);
		} catch (PDOException $e) {
			$this->_connectError($e);
		}

		if ($config['encoding']) {
			$this->encoding($config['encoding']);
		}

		return $this->_connection;
	}

	/**
	 * Manage connection error
	 *
	 * @param  PDOException $e A PDOException.
	 * @throws chaos\SourceException
	 */
	protected function _connectError($e) {
		$config = $this->_config;
		$code = $e->getCode();
		$msg = $e->getMessage();
		switch (true) {
			case $code === 'HY000' || substr($code, 0, 2) === '08':
				$msg = "Unable to connect to host `{$config['host']}`.";
			break;
			case in_array($code, array('28000', '42000')):
				$msg = "Host connected, but could not access database `{$config['database']}`.";
			break;
		}
		throw new SourceException($msg, $code, $e);
	}

	/**
	 * Returns an SQL builder instance.
	 *
	 * @return object.
	 */
	public function sql() {
		$sql = $this->_classes['sql'];
		return new $sql(['adapter' => $this]);
	}

	/**
	 * Returns the list of tables in the currently-connected database.
	 *
	 * @return array Returns an array of sources to which models can connect.
	 */
	public function sources($class = null) {
		$sql = $this->sql();
		$sql->select('TABLE_NAME')
			->from(['information_schema' => ['TABLES']])
			->where([
				'TABLE_TYPE' => 'BASE TABLE',
				'TABLE_SCHEMA' => $this->_config['database']
			]);

		$statement = $this->execute($sql);
		$result = $statement->fetchAll(PDO::FETCH_NUM);

		$sources = [];
		foreach($result as $source) {
			$sources[] = reset($source);
		}
		return $sources;
	}

	public function read($data = []) {
		$defaults = [
			'source' => null,
			'fields' => [],
			'alias' => null,
			'joins' => [],
			'conditions' => [],
			'group' => [],
			'having' => [],
			'order' => [],
			'offset' => null,
			'limit' => null,
			'comment' => null
		];

		$data += $defaults;

		$query  = $this->select($data['fields']);
		$query .= $this->from($data['source'], $data['alias']);
		foreach ($data['joins'] as $join) {
			$query .= $this->join($join);
		}
		$query .= $this->where($data['joins']);
		$query .= $this->group($data['group']);
		$query .= $this->having($data['having']);
		$query .= $this->order($data['order']);
		$query .= $this->limit($data['limit']);
		$query .= $this->comment($data['comment']);
	}

	public function update($data) {
	}

	public function delete($data) {

	}

	/**
	 * Quote a value.
	 *
	 * @param string $value The value to quote.
	 * @return string
	 */
	public function value($value) {
		if (is_string($value)) {
			return $this->_connection->quote($value, PDO::PARAM_STR);
		}
		if (is_null($value)) {
			return 'NULL';
		}
		if (is_bool($value)) {
			return $value ? 'TRUE' : 'FALSE';
		}
		if (is_float($value)) {
			return str_replace(',', '.', strval($value));
		}
		if (is_object($value) && isset($value->scalar)) {
			return $value->scalar;
		}
		return $value;
	}

	/**
	 * Escapes a column/table/schema with dotted syntax support.
	 *
	 * @param string $name Identifier name.
	 * @return string
	 */
	public function escape($name) {
		list($first, $second) = $this->undot($name);
		if ($first) {
			return $this->_escape($first) . '.' . $this->_escape($second);
		}
		return  $this->_escape($name);
	}

	/**
	 * Escapes a column/table/schema name.
	 *
	 * @param string $name Identifier name.
	 * @return string
	 */
	public function _escape($name) {
		$quote = $this->_escape;
		if (is_string($name) && preg_match('/^[a-z0-9_-]+$/i', $name)) {
			return $quote . $name . $quote;
		}
		return $name;
	}

	/**
	 * Split dotted syntax into distinct name.
	 *
	 * @param string $field A dotted identifier.
	 * @return array
	 */
	public function undot($field) {
		if (is_string($field) && preg_match('/^[a-z0-9_-]+\.([a-z 0-9_-]+|\*)$/i', $field)) {
			return explode('.', $field, 2);
		}
		return [null, $field];
	}

	/**
	 * Find records with custom SQL query.
	 *
	 * @param  string       $sql  SQL query to execute.
	 * @param  array        $data Array of bound parameters to use as values for query.
	 * @return PDOStatement A PDOStatement
	 * @throws chaos\SourceException
	 */
	public function execute($sql, $data = []){
		$statement = $this->_connection->prepare($sql);
		$statement->execute($data);
		return $statement;
	}

	/**
	 * Get the last insert id from the database.
	 * Abstract. Must be defined by child class.
	 *
	 * @param $query lithium\data\model\Query $context The given query.
	 */
	public function lastInsertId($source = null, $field = null) {
		return $this->_connection->lastInsertId();
	}

	/**
	 * Create a database-native table
	 *
	 * @param string $name A table name.
	 * @param object $schema A `Schema` instance.
	 * @return boolean `true` on success, `true` otherwise
	 */
	public function createSchema($source, $schema) {
		if (!$schema instanceof $this->_classes['schema']) {
			throw new InvalidArgumentException("Passed schema is not a valid `{$class}` instance.");
		}

		$columns = array();
		$primary = null;

		$source = $this->escape($source);

		foreach ($schema->fields() as $name => $field) {
			$field['name'] = $name;
			if ($field['type'] === 'id') {
				$primary = $name;
			}
			$columns[] = $this->column($field);
		}
		$columns = join(",\n", array_filter($columns));

		$metas = $schema->meta() + array('table' => array(), 'constraints' => array());

		$constraints = $this->_buildConstraints($metas['constraints'], $schema, ",\n", $primary);
		$table = $this->_buildMetas('table', $metas['table']);

		$params = compact('source', 'columns', 'constraints', 'table');
		return $this->_execute($this->renderCommand('schema', $params));
	}

	/**
	 * Drop a table
	 *
	 * @param string $name The table name to drop.
	 * @param boolean $soft With "soft dropping", the function will retrun `true` even if the
	 *                table doesn't exists.
	 * @return boolean `true` on success, `false` otherwise
	 */
	public function dropSchema($source, $soft = true) {
		$source = $this->escape($source);
		$exists = $soft ? 'IF EXISTS ' : '';
		return !!$this->execute("DROP TABLE {$exists}{$source}");
	}

	public function create($data) {
		$defaults = [
			'source' => null,
			'fields' => [],
			'values' => [],
			'comment' => null
		];

		$data += $defaults;

		$query  = $this->insert($data['source'], $data['fields'], $data['values']);
		$query .= $this->comment($data['comment']);
	}

	/**
	 * Generate a database-native column schema string
	 *
	 * @param array $column A field array structured like the following:
	 *        `array('name' => 'value', 'type' => 'value' [, options])`, where options can
	 *        be `'default'`, `'null'`, `'length'` or `'precision'`.
	 * @return string SQL string
	 */
	public function column($field) {
		if (!isset($field['type'])) {
			$field['type'] = 'string';
		}

		if (!isset($field['name'])) {
			throw new InvalidArgumentException("Column name not defined.");
		}

		if (!isset($this->_columns[$field['type']])) {
			throw new UnexpectedValueException("Column type `{$field['type']}` does not exist.");
		}

		$field += $this->_columns[$field['type']];

		$field += array(
			'name' => null,
			'type' => null,
			'length' => null,
			'precision' => null,
			'default' => null,
			'null' => null
		);

		$isNumeric = preg_match('/^(integer|float|boolean)$/', $field['type']);
		if ($isNumeric && $field['default'] === '') {
			$field['default'] = null;
		}
		$field['use'] = strtolower($field['use']);
		return $this->_buildColumn($field);
	}

	/**
	 * Helper for building columns metas
	 *
	 * @see DatabaseSchema::createSchema()
	 * @see DatabaseSchema::_column()
	 * @param array $metas The array of column metas.
	 * @param array $names If `$names` is not `null` only build meta present in `$names`
	 * @param type $joiner The join character
	 * @return string The SQL constraints
	 */
	protected function _buildMetas($type, array $metas, $names = null, $joiner = ' ') {
		$result = '';
		$names = $names ? (array) $names : array_keys($metas);
		foreach ($names as $name) {
			$value = isset($metas[$name]) ? $metas[$name] : null;
			if ($value && $meta = $this->_meta($type, $name, $value)) {
				$result .= $joiner . $meta;
			}
		}
		return $result;
	}

	/**
	 * Build a SQL column/table meta
	 *
	 * @param string $type The type of the meta to build (possible values: 'table' or 'column')
	 * @param string $name The name of the meta to build
	 * @param mixed $value The value used for building the meta
	 * @return string The SQL meta string
	 */
	protected function _meta($type, $name, $value) {
		$meta = isset($this->_metas[$type][$name]) ? $this->_metas[$type][$name] : null;
		if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
			return;
		}
		$meta += array('keyword' => '', 'escape' => false, 'join' => ' ');
		extract($meta);
		if ($escape === true) {
			$value = $this->value($value, array('type' => 'string'));
		}
		$result = $keyword . $join . $value;
		return $result !== ' ' ? $result : '';
	}

	/**
	 * Helper for building columns constraints
	 *
	 * @see DatabaseSchema::createSchema()
	 * @param array $constraints The array of constraints
	 * @param type $schema The schema of the table
	 * @param type $joiner The join character
	 * @return string The SQL constraints
	 */
	protected function _buildconstraints(array $constraints, $schema = null, $joiner = ' ', $primary = false) {
		$result = '';
		foreach ($constraints as $constraint) {
			if (isset($constraint['type'])) {
				$name = $constraint['type'];
				if ($meta = $this->_constraint($name, $constraint, $schema)) {
					$result .= $joiner . $meta;
				}
				if ($name === 'primary') {
					$primary = false;
				}
			}
		}
		if ($primary) {
			$result .= $joiner . $this->_constraint('primary', array('column' => $primary));
		}
		return $result;
	}

	/**
	 * Build a SQL column constraint
	 *
	 * @param string $name The name of the meta to build
	 * @param mixed $value The value used for building the meta
	 * @param object $schema A `Schema` instance.
	 * @return string The SQL meta string
	 */
	protected function _constraint($name, $value, $schema = null) {
		$value += array('options' => array());
		$meta = isset($this->_constraints[$name]) ? $this->_constraints[$name] : null;
		$template = isset($meta['template']) ? $meta['template'] : null;
		if (!$template) {
			return;
		}

		$data = array();
		foreach ($value as $name => $value) {
			switch ($name) {
				case 'key':
				case 'index':
					if (isset($meta[$name])) {
						$data['index'] = $meta[$name];
					}
				break;
				case 'to':
					$data[$name] = $this->escape($value);
				break;
				case 'on':
					$data[$name] = "ON {$value}";
				break;
				case 'expr':
					if (is_array($value)) {
						$result = array();
						$context = new Query(array('type' => 'none'));
						foreach ($value as $key => $val) {
							$return = $this->_processConditions($key, $val, $context, $schema);
							if ($return) {
								$result[] = $return;
							}
						}
						$data[$name] = join(" AND ", $result);
					} else {
						$data[$name] = $value;
					}
				break;
				case 'toColumn':
				case 'column':
					$data[$name] = join(', ', array_map(array($this, 'name'), (array) $value));
				break;
			}
		}

		return trim(String::insert($template, $data, array('clean' => array('method' => 'text'))));
	}

	/**
	 * Retrieves database error message and error code.
	 *
	 * @return array
	 */
	public function error() {
		if ($error = $this->_connection->errorInfo()) {
			return [$error[1], $error[2]];
		}
	}

	/**
	 * Disconnects the adapter from the database.
	 *
	 * @return boolean Returns `true` on success, else `false`.
	 */
	public function disconnect() {
		$this->_connection = null;
		return true;
	}

	/**
	 * Getter/Setter for the connection's encoding
	 * Abstract. Must be defined by child class.
	 *
	 * @param mixed $encoding
	 * @return mixed.
	 */
	abstract public function encoding($encoding = null);
}

?>