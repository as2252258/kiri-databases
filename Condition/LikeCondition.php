<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class LikeCondition
 * @package Database\Condition
 */
class LikeCondition extends Condition
{

	public string $pos = '';

	/**
	 * @return string
	 */
	public function builder(): string
	{
		if (!is_string($this->value)) {
			$this->value = array_shift($this->value);
		}
		return $this->column . ' LIKE \'%' . addslashes($this->value) . '%\'';
	}

}
