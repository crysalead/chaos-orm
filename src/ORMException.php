<?php
namespace Chaos\ORM;

/**
 * The `ChaosException` is thrown when a operation fails at the model layer.
 */
class ORMException extends \Exception
{
	protected $code = 500;
}
