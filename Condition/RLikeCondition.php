<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class RLikeCondition
 * @package Database\Condition
 */
class RLikeCondition extends Condition
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
		return sprintf('%s LIKE \'%s\'', $this->column, addslashes($this->value));
	}

}
