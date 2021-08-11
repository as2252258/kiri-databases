<?php
declare(strict_types=1);

namespace Database\Condition;


use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\BaseObject;
use Kiri\Core\Str;

/**
 * Class Condition
 * @package Database\Condition
 */
abstract class Condition extends BaseObject
{

	protected string $column = '';
	protected string $opera = '=';

	/** @var array|mixed */
	protected $value;

	const INT_TYPE = ['bit', 'bool', 'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'timestamp'];

	protected array $attributes = [];

	abstract public function builder();

	/**
	 * @param string $column
	 */
	public function setColumn(string $column): void
	{
		$this->column = $column;
	}

	/**
	 * @param string $opera
	 */
	public function setOpera(string $opera): void
	{
		$this->opera = $opera;
	}

	/**
	 * @param $params
	 */
	public function setValue($params): void
	{
		if (is_array($params)) {
			$values = [];
			foreach ($params as $item => $value) {
				$values[$item] = is_numeric($value) ? $value : '\'' . $value . '\'';
			}
			$this->value = $values;
		} else {
			$this->value = $this->checkIsSqlString($params);
		}
	}


	/**
	 * @param $params
	 * @return int|string
	 */
	#[Pure] private function checkIsSqlString($params): int|string
	{
		if (is_numeric($params)) {
			return $params;
		}

		$check = ltrim($params, '(');
		$check = strtolower(substr($check, 0, 6));
		if (in_array($check, ['update', 'select', 'insert', 'delete'])) {
			return $params;
		} else {
			return sprintf('\'%s\'', $params);
		}
	}


}
