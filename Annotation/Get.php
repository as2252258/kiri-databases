<?php


namespace Database\Annotation;


use Attribute;
use Kiri\Annotation\AbstractAttribute;


/**
 * Class Get
 * @package Annotation\Model
 */
#[Attribute(Attribute::TARGET_METHOD)] class Get extends AbstractAttribute
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
		return true;
	}


}
