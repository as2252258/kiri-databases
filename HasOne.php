<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:47
 */
declare(strict_types=1);

namespace Database;

use Database\Traits\HasBase;
use Exception;

/**
 * Class HasOne
 * @package Database
 * @internal Query
 */
class HasOne extends HasBase
{

	/**
	 * @param $name
	 * @param $arguments
	 * @return ActiveQuery|static
	 */
	public function __call($name, $arguments)
	{
		if (method_exists($this, $name)) {
			return call_user_func([$this, $name], ...$arguments);
		}
		$this->_relation->getQuery($this->model::className())->$name(...$arguments);
		return $this;
	}

	/**
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		return $this->_relation->first($this->model::className(), $this->value);
	}
}
