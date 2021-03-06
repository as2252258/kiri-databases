<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:58
 */
declare(strict_types=1);

namespace Database;

use Database\Traits\HasBase;
use Exception;

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
	 * @return static
	 */
	public function __call($name, $arguments)
	{
		if (!method_exists($this, $name)) {
            $this->_relation->getQuery($this->model::className())->$name(...$arguments);
		} else {
            call_user_func([$this, $name], ...$arguments);
        }
		return $this;
	}

	/**
	 * @return array|null|Collection
	 * @throws Exception
	 */
	public function get(): array|Collection|null
	{
		return $this->_relation->get($this->model::className(), $this->value);
	}
}
