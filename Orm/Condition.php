<?php
declare(strict_types=1);

namespace Database\Orm;


use Database\Traits\Builder;
use Exception;

/**
 * Trait Condition
 * @package Database\Orm
 */
trait Condition
{


	use Builder;

	/**
	 * @param $query
	 * @return string
	 * @throws Exception
	 */
	public function getWhere($query): string
	{
		return $this->where($query);
	}
}
