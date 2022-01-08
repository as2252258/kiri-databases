<?php


namespace Database\Annotation;


use Kiri\Annotation\Attribute;
use Database\Base\Setter;
use Exception;

#[\Attribute(\Attribute::TARGET_METHOD)] class Set extends Attribute
{


	/**
	 * Set constructor.
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
    public function execute(mixed $class, mixed $method = null): bool
	{
		di(Setter::class)->addSetter($this->name, $class, $method);
		return true;
	}


}
