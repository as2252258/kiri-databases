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
	 * @return static
	 */
	public function __call($name, $arguments)
	{
		if (!method_exists($this, $name)) {
			$key = $this->model::className() . '_' . $this->primaryId . '_' . $this->value;
			$this->_relation->getQuery($key)->$name(...$arguments);
		} else {
            call_user_func([$this, $name], ...$arguments);
        }
		return $this;
	}

	/**
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		$key = $this->model::className() . '_' . $this->primaryId . '_' . $this->value;
		return $this->_relation->first($key);
	}
}
