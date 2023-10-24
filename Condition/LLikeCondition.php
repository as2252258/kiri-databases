<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class LLikeCondition
 * @package Database\Condition
 */
class LLikeCondition extends Condition
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
		return $this->column . ' LIKE \'%' . addslashes($this->value) . '\'';
	}

}
