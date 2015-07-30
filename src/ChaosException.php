<?php
namespace chaos;

/**
 * The `ChaosException` is thrown when a operation fails at the model layer.
 */
class ChaosException extends \Exception
{
	protected $code = 500;
}
