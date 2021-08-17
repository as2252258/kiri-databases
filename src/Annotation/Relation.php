<?php


namespace Database\Annotation;


use Annotation\Attribute;
use Database\ActiveRecord;
use Database\Base\Relate;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Kiri;


/**
 * Class Relation
 * @package Annotation\Model
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
        di(Relate::class)->addRelate($class, $this->name, $method);
        return true;
	}

}
