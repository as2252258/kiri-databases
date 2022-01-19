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
	 * @throws ActiveQuery|static
	 */
	public function __call($name, $arguments)
	{
		if (method_exists($this, $name)) {
			return call_user_func([$this, $name], ...$arguments);
		}
		return $this->_relation->getQuery($this->model::className())->$name(...$arguments);
	}

	/**
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		return $this->_relation->count($this->model::className(), $this->value);
	}

}
