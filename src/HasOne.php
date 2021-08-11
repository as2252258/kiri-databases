<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:47
 */
declare(strict_types=1);

namespace Database;

use Exception;
use Database\Traits\HasBase;

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
	 * @return ActiveQuery
	 */
	public function __call($name, $arguments): mixed
	{
		if (method_exists($this, $name)) {
			return call_user_func([$this, $name], ...$arguments);
		}
		$this->_relation->getQuery($this->model::className())->$name(...$arguments);
		return $this;
	}

	/**
	 * @return array|null|ActiveRecord
	 * @throws Exception
	 */
	public function get(): array|ActiveRecord|null
	{
		return $this->_relation->first($this->model::className(), $this->value);
	}
}
