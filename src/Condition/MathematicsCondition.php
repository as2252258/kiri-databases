<?php
declare(strict_types=1);

namespace Database\Condition;

/**
 * Class MathematicsCondition
 * @package Database\Condition
 */
class MathematicsCondition extends Condition
{

	public string $type = '';

	/**
	 * @return mixed
	 */
	public function builder(): mixed
	{
		return $this->{strtolower($this->type)}((float)$this->value);
	}

	/**
	 * @param $value
	 * @return string
	 */
	public function eq($value): string
	{
		return $this->column . ' = ' . $value;
	}

	/**
	 * @param $value
	 * @return string
	 */
	public function neq($value): string
	{
		return $this->column . ' <> ' . $value;
	}

	/**
	 * @param $value
	 * @return string
	 */
	public function gt($value): string
	{
		return $this->column . ' > ' . $value;
	}

	/**
	 * @param $value
	 * @return string
	 */
	public function egt($value): string
	{
		return $this->column . ' >= ' . $value;
	}


	/**
	 * @param $value
	 * @return string
	 */
	public function lt($value): string
	{
		return $this->column . ' < ' . $value;
	}

	/**
	 * @param $value
	 * @return string
	 */
	public function elt($value): string
	{
		return $this->column . ' <= ' . $value;
	}

}
