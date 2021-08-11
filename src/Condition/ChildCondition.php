<?php
declare(strict_types=1);

namespace Database\Condition;


/**
 * Class ChildCondition
 * @package Database\Condition
 */
class ChildCondition extends Condition
{

	/**
	 * @return string
	 */
	public function builder(): string
	{
		return $this->column . ' ' . $this->opera . ' (' . $this->value . ')';
	}

}
