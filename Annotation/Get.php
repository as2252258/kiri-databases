<?php


namespace Database\Annotation;


use Attribute;


/**
 * Class Get
 * @package Annotation\Model
 * @deprecated
 */
#[Attribute(Attribute::TARGET_METHOD)] class Get
{
	
	
	/**
	 * Get constructor.
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
//		$keys = \Kiri::getDi()->get(Getter::class);
//		$keys->write($this->name, $class, $method);
		return true;
	}
	
	
}
