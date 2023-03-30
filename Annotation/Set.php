<?php


namespace Database\Annotation;


use Database\Base\Setter;
use Kiri\Annotation\AbstractAttribute;

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
		$keys = \Kiri::getDi()->get(Setter::class);
		$keys->write($this->name, $class, $method);
		return true;
	}


}
