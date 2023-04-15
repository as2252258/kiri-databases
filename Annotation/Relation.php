<?php


namespace Database\Annotation;


use Exception;


/**
 * Class Relation
 * @package Annotation\Model
 */
#[\Attribute(\Attribute::TARGET_METHOD)] class Relation
{


	/**
	 * Relation constructor.
	 * @param string $name
	 */
	public function __construct(public string $name)
	{
	}


	/**
	 * @param mixed $class
	 * @param mixed|null $method
	 * @return bool
	 * @throws Exception
	 */
	public function execute(mixed $class, mixed $method = null): bool
	{
		return true;
	}

}
