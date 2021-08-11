<?php
declare(strict_types=1);

namespace Database;

use Exception;
use Database\Traits\HasBase;

/**
 * Class HasCount
 * @package Database
 */
class HasCount extends HasBase
{

	/**
	 * @param $name
	 * @param $arguments
	 * @return ActiveQuery
	 * @throws Exception
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
		return $this->_relation->count($this->model::className(), $this->value);
	}

}
