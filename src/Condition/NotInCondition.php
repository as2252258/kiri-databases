<?php
declare(strict_types=1);

namespace Database\Condition;

use JetBrains\PhpStorm\Pure;

/**
 * Class NotInCondition
 * @package Database\Condition
 */
class NotInCondition extends Condition
{


	/**
	 * @return string|null
	 */
	#[Pure] public function builder(): ?string
	{
		if (!is_array($this->value)) {
			return null;
		}
		$value = '\'' . implode('\',\'', $this->value) . '\'';
		return '`' . $this->column . '` not in(' . $value . ')';
	}

}
