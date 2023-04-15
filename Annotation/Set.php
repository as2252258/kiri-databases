<?php


namespace Database\Annotation;




/**
 * @deprecated
 */
#[\Attribute(\Attribute::TARGET_METHOD)] class Set
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
//		$keys = \Kiri::getDi()->get(Setter::class);
//		$keys->write($this->name, $class, $method);
		return true;
	}


}
