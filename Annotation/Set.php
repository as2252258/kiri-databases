<?php


namespace Database\Annotation;


use Kiri\Annotation\AbstractAttribute;
use Exception;

#[\Attribute(\Attribute::TARGET_METHOD)] class Set extends AbstractAttribute
{


	/**
	 * Set constructor.
	 * @param string $name
	 */
	public function __construct(public string $name)
	{
	}


	/**
	 * @param mixed $class
	 * @param mixed|null $method
	 * @return bool
	 */
    public function execute(mixed $class, mixed $method = null): bool
	{
		return true;
	}


}
