<?php
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri\Abstracts\Component;

/**
 * Class Relation
 * @package Kiri\db
 */
class Relation extends Component
{

	private array $_relations = [];

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
	 * @param string $name
	 * @return ActiveQuery|null
	 */
	public function getQuery(string $name): ?ActiveQuery
	{
		return $this->_query[$name] ?? null;
	}


	/**
	 * @param string $identification
	 * @param $localValue
	 * @return mixed
	 * @throws Exception
	 */
	public function first(string $identification, $localValue): mixed
	{
		$_identification = $identification . '_first_' . $localValue;
		if (isset($this->_relations[$_identification]) && $this->_relations[$_identification] !== null) {
			return $this->_relations[$_identification];
		}

		$activeModel = $this->_query[$identification]->first();
		if (empty($activeModel)) {
			return null;
		}

		return $this->_relations[$_identification] = $activeModel;
	}


	/**
	 * @param string $identification
	 * @param $localValue
	 * @return mixed
	 * @throws Exception
	 */
	public function count(string $identification, $localValue): mixed
	{
		$_identification = $identification . '_count_' . $localValue;
		if (isset($this->_relations[$_identification]) && $this->_relations[$_identification] !== null) {
			return $this->_relations[$_identification];
		}

		$activeModel = $this->_query[$identification]->count();
		if (empty($activeModel)) {
			return null;
		}

		return $this->_relations[$_identification] = $activeModel;
	}


	/**
	 * @param string $identification
	 * @param $localValue
	 * @return mixed
	 */
	public function get(string $identification, $localValue): mixed
	{
		if (is_array($localValue)) {
			$_identification = $identification . '_get_' . implode('_', $localValue);
		} else {
			$_identification = $identification . '_get_' . $localValue;
		}
		if (isset($this->_relations[$_identification]) && $this->_relations[$_identification] !== null) {
			return $this->_relations[$_identification];
		}

		$activeModel = $this->_query[$identification]->get();
		if (empty($activeModel)) {
			return null;
		}

		return $this->_relations[$_identification] = $activeModel;
	}

}
