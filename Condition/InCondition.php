<?php
declare(strict_types=1);

namespace Database\Condition;

use Database\ActiveQuery;
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
			return sprintf('%s IN (%s)', $this->column, implode(',', $this->value));
		} else {
			return sprintf('%s IN (%s)', $this->column, $this->value);
		}
	}

}
