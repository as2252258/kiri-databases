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
	 * @return array|null|Collection
	 * @throws Exception
	 */
	public function get(): array|Collection|null
	{
		$key = $this->model . '_' . $this->primaryId . '_' . $this->value;
		return $this->_relation->get(md5($key));
	}
}
