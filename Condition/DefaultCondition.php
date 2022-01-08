<?php
declare(strict_types=1);

namespace Database\Condition;


use JetBrains\PhpStorm\Pure;

/**
 * Class DefaultCondition
 * @package Database\Condition
 */
class DefaultCondition extends Condition
{

	/**
	 * @return string
	 */
	#[Pure] public function builder(): string
	{
		return sprintf('%s %s %s', $this->column, $this->opera, addslashes($this->value));
	}

}
