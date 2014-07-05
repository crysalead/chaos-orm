<?php
namespace chaos\source\database;

use PDO;
use PDOStatement;
use PDOException;

/**
 * This class is a wrapper around the MySQL result returned and can be used to iterate over it.
 *
 * It also provides a simple caching mechanism which stores the result after the first load.
 * You are then free to iterate over the result back and forth through the provided methods
 * and don't have to think about hitting the database too often.
 *
 * On initialization, it needs a `PDOStatement` to operate on. You are then free to use all
 * methods provided by the `Iterator` interface.
 *
 * @link http://php.net/manual/de/class.pdostatement.php The PDOStatement class.
 * @link http://php.net/manual/de/class.iterator.php The Iterator interface.
 */
class Result extends \chaos\source\Result {

	/**
	 * Fetches the result from the resource and caches it.
	 *
	 * @return boolean Return `true` on success or `false` if it is not valid.
	 */
	protected function _fetchFromResource() {
		if (!$this->_resource instanceof PDOStatement) {
			$this->_resource = null;
			return false;
		}
		try {
			if ($result = $this->_resource->fetch(PDO::FETCH_NUM)) {
				$this->_key = $this->_iterator;
				$this->_current = $this->_cache[$this->_iterator++] = $result;
				return true;
			}
		} catch (PDOException $e) {
			return false;
		}
		return false;
	}

}

?>