<?php
declare(strict_types=1);

namespace Database\Condition;


use JetBrains\PhpStorm\Pure;

/**
 * Class OrCondition
 * @package Database\Condition
 */
class OrCondition extends Condition
{

	public array $oldParams = [];


	/**
	 * @return string
	 */
	#[Pure] public function builder(): string
	{
		return '(' . implode(' AND ', $this->oldParams) . ') OR ' . addslashes($this->value);
	}

}
