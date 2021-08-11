<?php
declare(strict_types=1);

namespace Database\Condition;


/**
 * Class BetweenCondition
 * @package Database\Condition
 */
class BetweenCondition extends Condition
{


	/**
	 * @return string
	 */
	public function builder(): string
	{
		return $this->column . ' BETWEEN ' . (int)$this->value[0] . ' AND ' . (int)$this->value[1];
	}

}
