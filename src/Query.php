<?php
namespace chaos;

/**
 * The `Query` class acts as a container for all information necessary to perform a
 * database operation.
 *
 * Each `Query` object instance has a type, which is usually one of `'create'`, `'read'`,
 * `'update'` or `'delete'`.
 *
 */
class Query {

	/**
	 * Stores configuration information for object instances at time of construction.
	 *
	 * @var array
	 */
	protected $_config = [];

	/**
	 * Class constructor, which initializes the default values this object supports.
	 * Even though only a specific list of configuration parameters is available
	 * by default, the `Query` object uses the `__call()` method to implement
	 * automatic getters and setters for any arbitrary piece of data.
	 *
	 * This means that any information may be passed into the constructor may be
	 * used by the backend data source executing the query (or ignored, if support
	 * is not implemented). This is useful if, for example, you wish to extend a
	 * core data source and implement custom fucntionality.
	 *
	 * @param array $config Config options:
	 *        - `'type'` _string_: The type of the query (`read`, `create`, `update`, `delete`).
	 *        - `'entity'` _object_: The base entity to query on.
	 *        - `'source'` _string_: The name of the table/collection. Unnecessary if `entity` is set.
	 *        - `'data'` _array_: Datas for update queries. Unnecessary if `entity` is set.
	 */
	public function __construct($config = []) {
		$defaults = array(
			'type' => 'read',
			'entity' => null,
			'source' => null,
			'data' => []
		);
		$this->_config = $config + $defaults;

		foreach ($this->_config as $key => $value) {
			$this->{$key}($value);
		}
	}

	/**
	 * Gets or sets a custom query field which does not have an accessor method.
	 *
	 * @param  string $method Query part.
	 * @param  array  $params Query parameters.
	 * @return mixed  Returns a value on get or `$this` on set.
	 */
	public function __call($method, $params = []) {
		if (!$params) {
			return isset($this->_config[$method]) ? $this->_config[$method] : null;
		}
		$this->_config[$method] = current($params);
		return $this;
	}

	/**
	 * Set and get method for query's limit of amount of records to return
	 *
	 * @param  integer $limit
	 * @return integer
	 */
	public function limit($limit = null) {
		if ($limit === null) {
			return $this->_config['limit'];
		}
		$this->_config['limit'] = $limit ? intval($limit) : null;
		return $this;
	}

	/**
	 * Set and get method for query's offset, i.e. which records to get
	 *
	 * @param  integer $offset
	 * @return integer
	 */
	public function offset($offset = null) {
		if ($offset === null) {
			return $this->_config['offset'];
		}
		$this->_config['offset'] = intval($offset);
		return $this;
	}

}

?>