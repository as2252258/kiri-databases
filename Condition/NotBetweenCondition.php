<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class NotBetweenCondition
 * @package Database\Condition
 */
class NotBetweenCondition extends Condition
{


	/**
	 * @return string
	 */
	public function builder(): string
	{
		return $this->column . ' NOT BETWEEN ' . (int)$this->value[0] . ' AND ' . (int)$this->value[1];
	}

}
