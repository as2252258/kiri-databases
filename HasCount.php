<?php
declare(strict_types=1);

namespace Database;

use Database\Traits\HasBase;
use Exception;

/**
 * Class HasCount
 * @package Database
 */
class HasCount extends HasBase
{

	/**
	 * @return array|null|ModelInterface
	 * @throws Exception
	 */
	public function get(): array|ModelInterface|null
	{
		$key = $this->model::className() . '_' . $this->primaryId . '_' . $this->value;
		return $this->_relation->count($key);
	}

}
