<?php


namespace Database\Traits;


use Database\ActiveQuery;
use Database\ISqlBuilder;
use Exception;
use JetBrains\PhpStorm\Pure;


/**
 * Class CaseWhen
 * @package Database\Traits
 */
class When
{

	public ActiveQuery|ISqlBuilder $query;


	private array $_condition = [];

	private string $else = '';


	/**
	 * CaseWhen constructor.
	 * @param string $column
	 * @param ActiveQuery|ISqlBuilder $activeQuery
	 */
	public function __construct(public string $column, public ActiveQuery|ISqlBuilder $activeQuery)
	{
		$this->_condition[] = 'CASE ' . $column;
	}


	/**
	 * @param string|int $condition
	 * @param string $then
	 * @return $this
	 * @throws Exception
	 */
	public function when(string|int $condition, string $then): static
	{
		$this->_condition[] = sprintf('WHEN %s THEN %s', $condition, $then);

		return $this;
	}


	/**
	 * @param string $alias
	 */
	public function else(string $alias)
	{
		$this->else = $alias;
	}


	/**
	 * @return string
	 */
	#[Pure] public function end(): string
	{
		if (empty($this->_condition)) {
			return '';
		}
		$prefix = implode(' ', $this->_condition);
		if (!empty($this->else)) {
			$prefix .= ' ELSE ' . $this->else;
		}
		return '(' . $prefix . ' END)';
	}

}
