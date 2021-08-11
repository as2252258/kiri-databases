<?php
declare(strict_types=1);

namespace Database\Condition;

use Kiri\Core\Str;

/**
 * Class NotLikeCondition
 * @package Database\Condition
 */
class NotLikeCondition extends Condition
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
		return $this->column . ' NOT LIKE \'%' . addslashes($this->value) . '%\'';
	}

}
