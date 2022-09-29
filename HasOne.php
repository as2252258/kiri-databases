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
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		$key = $this->model::className() . '_' . $this->primaryId . '_' . $this->value;
		return $this->_relation->first(md5($key));
	}
}
