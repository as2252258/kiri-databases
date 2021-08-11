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
	 */
	public function builder(): string
	{
		$array = [];
		if (empty($this->value)) {
			return '';
		}
		foreach ($this->value as $key => $value) {
			if ($value === null) {
				continue;
			}
			$array[] = sprintf("%s = '%s'", $key, addslashes($value));
		}
		return implode(' AND ', $array);
	}

}
