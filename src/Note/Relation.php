<?php


namespace Database\Note;


use Note\Attribute;
use Database\Base\Relate;
use Exception;


/**
 * Class Relation
 * @package Note\Model
 */
#[\Attribute(\Attribute::TARGET_METHOD)] class Relation extends Attribute
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
		di(Relate::class)->addRelate($this->name, $class, $method);
		return true;
	}

}
