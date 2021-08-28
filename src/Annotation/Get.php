<?php


namespace Database\Annotation;


use Attribute;
use Database\Base\Getter;
use Exception;


/**
 * Class Get
 * @package Annotation\Model
 */
#[Attribute(Attribute::TARGET_METHOD)] class Get extends \Annotation\Attribute
{


	/**
	 * Get constructor.
	 * @param string $name
	 */
	public function __construct(public string $name)
	{
	}


    /**
     * @param static $params
     * @param mixed $class
     * @param mixed|null $method
     * @return bool
     * @throws \Kiri\Exception\NotFindClassException
     * @throws \ReflectionException
     */
    public static function execute(mixed $params, mixed $class, mixed $method = null): bool
	{
		di(Getter::class)->addGetter($params->name, $class, $method);
		return true;
	}


}
