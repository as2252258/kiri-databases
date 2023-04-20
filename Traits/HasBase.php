<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 15:47
 */
declare(strict_types=1);

namespace Database\Traits;

use Database\ModelInterface;
use Database\Collection;
use Database\Relation;
use Kiri;

/**
 * Class HasBase
 * @package Database
 *
 * @include Query
 *
 * @method first($name)
 * @method all($name)
 * @method count($name)
 */
abstract class HasBase implements \Database\Traits\Relation
{
	
	/** @var ModelInterface|Collection */
	protected mixed $data = null;
	
	/**
	 * @var ModelInterface
	 */
	protected mixed $model;
	
	
	protected mixed $value = 0;
	
	
	/**
	 * HasBase constructor.
	 * @param string $name
	 */
	public function __construct(public string $name)
	{
	}

	/**
	 * @param $name
	 * @param $arguments
	 * @return static
	 * @throws \ReflectionException
	 */
	public function __call($name, $arguments)
	{
		if ($name !== 'get') {
			$relation = Kiri::getDi()->get(Relation::class);
			$relation->getQuery($this->name)->$name(...$arguments);
			return $this;
		} else {
			return $this->get();
		}
	}
	
	
	/**
	 * @param $name
	 * @return mixed
	 */
	public function __get($name): mixed
	{
		if ($this->data === null) {
			$this->data = $this->get();
		}
		return $this->data;
	}
}
