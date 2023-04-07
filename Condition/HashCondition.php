<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class HashCondition
 * @package Yoc\db\condition
 */
class HashCondition extends Condition
{

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function builder(): string
	{
		$array = [];
		if (count($this->value) < 1) {
			throw new \Exception('Builder data by a empty array.');
		}
		foreach ($this->value as $key => $value) {
			$array[] = $key . '=' . addslashes($value);
		}
		return implode(' AND ', $array);
	}

}
