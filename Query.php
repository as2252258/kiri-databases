<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/6/27 0027
 * Time: 17:49
 */
declare(strict_types=1);

namespace Database;


use Database\Traits\QueryTrait;
use Exception;

/**
 * Class Query
 * @package Database
 */
class Query implements ISqlBuilder
{

	use QueryTrait;


	/**
	 * @throws Exception
	 */
	public function __construct()
	{
		$this->builder = SqlBuilder::builder($this);
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getSql(): string
	{
		return $this->builder->get();
	}


	/**
	 * @return string
	 * @throws Exception
	 */
	public function getCondition(): string
	{
		return $this->builder->getCondition();
	}


}
