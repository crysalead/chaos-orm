<?php
namespace chaos;

class Type {

	public static function cast($value) {
		return $value;
	}

	public static function dump($value) {
		return static::cast($value);
	}

}

?>