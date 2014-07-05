<?php
namespace chaos;

/**
 * The `SourceException` is thrown when a operation on a source returns an exception.
 */
class SourceException extends \PDOException {

	protected $code = 500;

}

?>