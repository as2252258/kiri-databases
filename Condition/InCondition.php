<?php
declare(strict_types=1);

namespace Database\Condition;

use Exception;
use JetBrains\PhpStorm\Pure;

/**
 * Class InCondition
 * @package Database\Condition
 */
class InCondition extends Condition
{


	/**
	 * @return string
	 * @throws Exception
	 */
	#[Pure] public function builder(): string
	{
		if (is_array($this->value)) {
			return $this->column . ' IN (' . implode(',', $this->value) . ')';
		} else {
			return $this->column . ' IN (' . $this->value . ')';
		}
	}

}
