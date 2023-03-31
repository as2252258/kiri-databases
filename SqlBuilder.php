<?php


namespace Database;


use Database\Traits\Builder;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;


/**
 * Class SqlBuilder
 * @package Database
 */
class SqlBuilder extends Component
{
	
	use Builder;
	
	
	public ActiveQuery|Query|null $query;
	
	
	/**
	 * @param ISqlBuilder|null $query
	 * @return $this
	 * @throws Exception
	 */
	public static function builder(ISqlBuilder|null $query): static
	{
		return new static(['query' => $query]);
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function getCondition(): string
	{
		return $this->conditionToString();
	}
	
	
	/**
	 * @param array $compiler
	 * @return string
	 * @throws Exception
	 */
	public function hashCompiler(array $compiler): string
	{
		return $this->where($compiler);
	}
	
	
	/**
	 * @param array $attributes
	 * @return bool|array
	 * @throws Exception
	 */
	public function update(array $attributes): bool|array
	{
		[$string, $array] = $this->builderParams($attributes);
		
		return $this->__updateBuilder($string, $array);
	}
	
	
	/**
	 * @param array $attributes
	 * @param string $opera
	 * @return bool|array
	 * @throws Exception
	 */
	public function mathematics(array $attributes, string $opera = '+'): bool|array
	{
		$string = [];
		foreach ($attributes as $attribute => $value) {
			$string[] = $attribute . '=' . $attribute . $opera . $value;
		}
		return $this->__updateBuilder($string, []);
	}
	
	
	/**
	 * @param array $string
	 * @param array $params
	 * @return array|bool
	 * @throws Exception
	 */
	private function __updateBuilder(array $string, array $params): array|bool
	{
		if (empty($string)) {
			return $this->logger->addError('None data update.');
		}
		
		$update = 'UPDATE ' . $this->tableName() . ' SET ' . implode(',', $string) . $this->_prefix();
		$update .= $this->builderLimit($this->query, false);
		
		return [$update, $params];
	}
	
	
	/**
	 * @param array $attributes
	 * @param false $isBatch
	 * @return array
	 * @throws Exception
	 */
	public function insert(array $attributes, bool $isBatch = false): array
	{
		$update = 'INSERT INTO ' . $this->tableName();
		if ($isBatch === false) {
			$attributes = [$attributes];
		}
		$update .= '(' . implode(',', $this->getFields($attributes)) . ') VALUES ';
		
		$order = 0;
		$keys = $params = [];
		foreach ($attributes as $attribute) {
			[$_keys, $params] = $this->builderParams($attribute, true, $params, $order);
			
			$keys[] = implode(',', $_keys);
			$order++;
		}
		return [$update . '(' . implode('),(', $keys) . ')', $params];
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function delete(): string
	{
		return 'DELETE FROM ' . $this->tableName() . ' WHERE ' . $this->_prefix();
	}
	
	
	/**
	 * @param $attributes
	 * @return array
	 */
	#[Pure] private function getFields($attributes): array
	{
		return array_keys(current($attributes));
	}
	
	
	/**
	 * @param array $attributes
	 * @param bool $isInsert
	 * @param array $params
	 * @param int $order
	 * @return array[]
	 * a=:b,
	 */
	#[Pure] private function builderParams(array $attributes, bool $isInsert = false, array $params = [], int $order = 0): array
	{
		$keys = [];
		foreach ($attributes as $key => $value) {
			if ($isInsert === true) {
				$keys[] = ':' . $key . $order;
				$params[$key . $order] = $value;
			} else {
				[$keys, $params] = $this->resolveParams($key, $value, $order, $params, $keys);
			}
		}
		return [$keys, $params];
	}
	
	
	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $order
	 * @param array $params
	 * @param array $keys
	 * @return array
	 */
	private function resolveParams(string $key, mixed $value, int $order, array $params, array $keys): array
	{
		if (is_null($value)) {
			return [$keys, $params];
		}
		if (
			str_starts_with($value, '+ ') ||
			str_starts_with($value, '- ')
		) {
			$keys[] = $key . '=' . $key . ' ' . $value;
		} else {
			$params[$key . $order] = $value;
			$keys[] = $key . '=:' . $key . $order;
		}
		return [$keys, $params];
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function one(): string
	{
		$this->query->limit(0, 1);
		return $this->_selectPrefix() . $this->_prefix();
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function all(): string
	{
		return $this->_selectPrefix() . $this->_prefix();
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function count(): string
	{
		$this->query->select('COUNT(*)');
		return $this->_selectPrefix() . $this->_prefix();
	}
	
	
	/**
	 * @param $table
	 * @return string
	 */
	public function columns($table): string
	{
		return 'SHOW FULL FIELDS FROM ' . $table;
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	private function _prefix(): string
	{
		$select = '';
		if (($condition = $this->conditionToString()) != '') {
			$select .= " WHERE $condition";
		}
		if (count($this->query->attributes) > 0) {
			$select = strtr($select, $this->query->attributes);
		}
		if ($this->query->group != "") {
			$select .= ' GROUP BY ' . $this->query->group;
		}
		if ($this->query->order != "") {
			$select .= ' ORDER BY ' . implode(',', $this->query->order);
		}
		return $select . $this->builderLimit($this->query);
	}
	
	/**
	 * @return string
	 * @throws Exception
	 */
	private function _selectPrefix(): string
	{
		if (count($this->query->select) < 1) {
			$this->query->select = ['*'];
		}
		$select = "SELECT " . implode(',', $this->query->select) . " FROM " . $this->tableName();
		if ($this->query->alias != "") {
			$select .= " AS " . $this->query->alias;
		}
		if (count($this->query->join) > 0) {
			$select .= ' ' . implode(' ', $this->query->join);
		}
		return $select;
	}
	
	
	/**
	 * @param false $isCount
	 * @return string
	 * @throws Exception
	 */
	public function get(bool $isCount = false): string
	{
		if ($isCount === false) {
			return $this->all();
		}
		return $this->count();
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function truncate(): string
	{
		return sprintf('TRUNCATE %s', $this->tableName());
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	private function conditionToString(): string
	{
		return $this->where($this->query->where);
	}
	
	
	/**
	 * @return string
	 * @throws Exception
	 */
	public function tableName(): string
	{
		if ($this->query->from instanceof \Closure) {
			$this->query->from = '(' . $this->query->makeClosureFunction($this->query->from) . ')';
		}
		if ($this->query->from instanceof ActiveQuery) {
			$this->query->from = '(' . SqlBuilder::builder($this->query->from)->get($this->query->from) . ')';
		}
		if ($this->query->from == "") {
			return $this->query->modelClass->getTable();
		} else {
			return $this->query->from;
		}
	}
	
}
