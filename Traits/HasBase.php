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
	protected Collection|ModelInterface $data;
	
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
		;
	}
	
	/**
	 * @param $name
	 * @param $arguments
	 * @return static
	 */
	public function __call($name, $arguments)
	{
		if (!method_exists($this, $name)) {
			$relation = Kiri::getDi()->get(Relation::class);
			$relation->getQuery($this->name)->$name(...$arguments);
		} else {
			call_user_func([$this, $name], ...$arguments);
		}
		return $this;
	}
	

	/**
	 * @param $name
	 * @return mixed
	 */
	public function __get($name): mixed
	{
		return $this->get();
	}
}
