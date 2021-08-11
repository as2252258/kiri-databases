<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:58
 */
declare(strict_types=1);
namespace Database;

use Exception;
use Database\Traits\HasBase;

/**
 * Class HasMany
 * @package Database
 *
 * @method with($name)
 */
class HasMany extends HasBase
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
		return $this->_relation->getQuery($this->model::className())->$name(...$arguments);
	}

	/**
	 * @return array|null|ActiveRecord
	 * @throws Exception
	 */
	public function get(): array|ActiveRecord|null
	{
		return $this->_relation->get($this->model::className(), $this->value);
	}
}
