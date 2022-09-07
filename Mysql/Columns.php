<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019-03-18
 * Time: 17:22
 */

declare(strict_types=1);

namespace Database\Mysql;


use Database\Connection;
use Database\SqlBuilder;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;
use Kiri\Core\Json;

/**
 * Class Columns
 * @package Database\Mysql
 */
class Columns extends Component
{

	/**
	 * @var array
	 * field types
	 */
	private array $columns = [];

	/**
	 * @var Connection
	 * Mysql client
	 */
	public Connection $db;

	/**
	 * @var string
	 * tableName
	 */
	public string $table = '';

	/**
	 * @var array
	 * field primary key
	 */
	private array $_primary = [];

	/**
	 * @var array
	 * by mysql field auto_increment
	 */
	private array $_auto_increment = [];


	private array $_fields = [];

	/**
	 * @param string $table
	 * @return $this
	 * @throws Exception
	 */
	public function table(string $table): static
	{
		$this->structure($this->table = $table);
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTable(): string
	{
		return $this->table;
	}

	/**
	 * @param $key
	 * @param $val
	 * @return mixed
	 * @throws Exception
	 */
	public function fieldFormat($key, $val): mixed
	{
		return $this->encode($val, $this->get_fields($key));
	}

	/**
	 * @param $data
	 * @return array
	 * @throws
	 */
	public function populate($data): array
	{
		$column = $this->get_fields();
		foreach ($data as $key => $val) {
			if (!isset($column[$key])) {
				continue;
			}
			$data[$key] = $this->decode($val, $column[$key]);
		}
		return $data;
	}

	/**
	 * @param $val
	 * @param null $format
	 * @return mixed
	 */
	public function decode($val, $format = null): mixed
	{
		if (empty($format) || $val === null) {
			return $val;
		}
		$format = strtolower($format);
		if ($this->isInt($format)) {
			return (int)$val;
		} else if ($this->isJson($format)) {
			return Json::decode($val, true);
		} else if ($this->isFloat($format)) {
			return (float)$val;
		} else {
			return stripslashes($val);
		}
	}


	/**
	 * @param string $name
	 * @param $value
	 * @return mixed
	 * @throws Exception
	 */
	public function _decode(string $name, $value): mixed
	{
		return $this->decode($value, $this->get_fields($name));
	}


	/**
	 * @param $val
	 * @param null $format
	 * @return float|bool|int|string
	 * @throws Exception
	 */
	public function encode($val, $format = null): float|bool|int|string
	{
		if (empty($format)) {
			return $val;
		}
		$format = strtolower($format);
		if ($this->isInt($format)) {
			return (int)$val;
		} else if ($this->isJson($format)) {
			return Json::encode($val);
		} else if ($this->isFloat($format)) {
			return (float)$val;
		} else {
			return addslashes($val);
		}
	}

	/**
	 * @param $format
	 * @return bool
	 */
	#[Pure] public function isInt($format): bool
	{
		return in_array($format, ['int', 'bigint', 'tinyint', 'smallint', 'mediumint']);
	}

	/**
	 * @param $format
	 * @return bool
	 */
	#[Pure] public function isFloat($format): bool
	{
		return in_array($format, ['float', 'double', 'decimal']);
	}

	/**
	 * @param $format
	 * @return bool
	 */
	#[Pure] public function isJson($format): bool
	{
		return $format == 'json';
	}

	/**
	 * @param $format
	 * @return bool
	 */
	#[Pure] public function isString($format): bool
	{
		return in_array($format, ['varchar', 'char', 'text', 'longtext', 'tinytext', 'mediumtext']);
	}


	/**
	 * @return array
	 * @throws
	 */
	public function format(): array
	{
		return $this->columns('Default', 'Field');
	}


	/**
	 * @return mixed
	 * @throws Exception
	 */
	public function getFields(): array
	{
		if (empty($this->_fields)) {
			$this->structure($this->table);
		}
		return $this->_fields[$this->table];
	}


	/**
	 * @param string $name
	 * @return bool
	 * @throws Exception
	 */
	public function hasField(string $name): bool
	{
		return array_key_exists($name, $this->getFields());
	}


	/**
	 * @return int|string|null
	 * @throws Exception
	 */
	public function getAutoIncrement(): int|string|null
	{
		return $this->_auto_increment[$this->table] ?? null;
	}

	/**
	 * @return array|null|string
	 *
	 * @throws Exception
	 */
	public function getPrimaryKeys(): array|string|null
	{
		if (isset($this->_auto_increment[$this->table])) {
			return $this->_auto_increment[$this->table];
		}
		return $this->_primary[$this->table] ?? null;
	}

	/**
	 * @return array|null|string
	 *
	 * @throws Exception
	 */
	#[Pure] public function getFirstPrimary(): array|string|null
	{
		if (isset($this->_auto_increment[$this->table])) {
			return $this->_auto_increment[$this->table];
		}
		if (isset($this->_primary[$this->table])) {
			return current($this->_primary[$this->table]);
		}
		return null;
	}

	/**
	 * @param $name
	 * @param null $index
	 * @return array
	 * @throws Exception
	 */
	private function columns($name, $index = null): array
	{
		if (empty($index)) {
			return array_column($this->getColumns(), $name);
		} else {
			return array_column($this->getColumns(), $name, $index);
		}
	}

	/**
	 * @return array|static
	 * @throws Exception
	 */
	private function getColumns(): array|static
	{
		return $this->structure($this->getTable());
	}


	/**
	 * @param $table
	 * @return array|Columns
	 * @throws Exception
	 */
	private function structure($table): array|static
	{
		if (!isset($this->columns[$table]) || empty($this->columns[$table])) {
			$column = $this->db->createCommand(SqlBuilder::builder(null)->columns($table))->all();
			if (empty($column)) {
				throw new Exception("The table " . $table . " not exists.");
			}
			return $this->columns[$table] = $this->resolve($column, $table);
		}
		return $this->columns[$table];
	}


	/**
	 * @param array $column
	 * @param $table
	 * @return array
	 */
	private function resolve(array $column, $table): array
	{
		foreach ($column as $key => $item) {
			$this->addPrimary($item, $table);
			$column[$key]['Type'] = $this->clean($item['Type']);
		}

		$this->_fields[$table] = array_column($column, 'Default', 'Field');

		return $column;
	}

	/**
	 * @param $item
	 * @param $table
	 */
	private function addPrimary($item, $table)
	{
		if (!isset($this->_primary[$table])) {
			$this->_primary[$table] = [];
		}
		if ($item['Key'] === 'PRI') {
			$this->_primary[$table][] = $item['Field'];
		}
		$this->addIncrement($item, $table);
	}


	/**
	 * @param $item
	 * @param $table
	 */
	private function addIncrement($item, $table)
	{
		if ($item['Extra'] !== 'auto_increment') {
			return;
		}
		$this->_auto_increment[$table] = $item['Field'];
	}


	/**
	 * @param $type
	 * @return string
	 */
	public function clean($type): string
	{
		if (!str_contains($type, ')')) {
			return $type;
		}
		$replace = preg_replace('/\(\d+(,\d+)?\)(\s+\w+)*/', '', $type);
		if (str_contains($replace, ' ')) {
			$replace = explode(' ', $replace)[1];
		}
		return $replace;
	}

	/**
	 * @param null $field
	 * @return array|string|null
	 * @throws Exception
	 */
	public function get_fields($field = null): array|string|null
	{
		$fields = $this->getAllField();
		if (empty($field)) {
			return $fields;
		}
		if (isset($fields[$field])) {
			return strtolower($fields[$field]);
		}
		return null;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getAllField(): array
	{
		return $this->columns('Type', 'Field');
	}

}
