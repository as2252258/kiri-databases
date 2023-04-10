<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri\Abstracts\Component;
use Kiri\Di\Context;

/**
 * Class Relation
 * @package Kiri\db
 */
class Relation extends Component
{

	/** @var ActiveQuery[] $_query */
	private array $_query = [];

	/**
	 * @param string $identification
	 * @param ActiveQuery $query
	 * @return $this
	 */
	public function bindIdentification(string $identification, ActiveQuery $query): static
	{
		$this->_query[$identification] = $query;
		return $this;
	}

	/**
	 * @param string $identification
	 * @return bool
	 */
	public function hasIdentification(string $identification): bool
	{
		return isset($this->_query[$identification]) && $this->_query[$identification] instanceof ActiveQuery;
	}

	/**
	 * @param string $name
	 * @return ActiveQuery|null
	 */
	public function getQuery(string $name): ?ActiveQuery
	{
		return $this->_query[$name] ?? null;
	}


	/**
	 * @param string $_identification
	 * @return mixed
	 * @throws Exception
	 */
	public function first(string $_identification): mixed
	{
		if (Context::exists($_identification)) {
			return Context::get($_identification);
		}
		$activeModel = $this->_query[$_identification]->first();
		if (empty($activeModel)) {
			return null;
		}
		unset($this->_query[$_identification]);
		return Context::set($_identification, $activeModel);
	}


	/**
	 * @param string $_identification
	 * @return mixed
	 */
	public function count(string $_identification): mixed
	{
		if (Context::exists($_identification)) {
			return Context::get($_identification);
		}
		$activeModel = $this->_query[$_identification]->count();
		if (empty($activeModel)) {
			return null;
		}
		unset($this->_query[$_identification]);
		return Context::set($_identification, $activeModel);
	}


	/**
	 * @param string $_identification
	 * @return mixed
	 */
	public function get(string $_identification): mixed
	{
		if (Context::exists($_identification)) {
			return Context::get($_identification);
		}
		$activeModel = $this->_query[$_identification]->get();
		if (empty($activeModel)) {
			return $activeModel;
		}
		unset($this->_query[$_identification]);
		return Context::set($_identification, $activeModel);
	}

}
