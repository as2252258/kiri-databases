<?php
declare(strict_types=1);

namespace Database;


use Closure;
use Exception;
use Kiri\Abstracts\Component;

/**
 * Class Pagination
 * @package Database
 */
class Pagination extends Component
{

	/** @var ActiveQuery */
	private ActiveQuery $activeQuery;

	/** @var int 从第几个开始查 */
	private int $_offset = 0;

	/** @var int 每页数量 */
	private int $_limit = 100;

	/** @var int 最大查询数量 */
	private int $_max = 0;

	/** @var int 当前已查询数量 */
	private int $_length = 0;

	/** @var Closure */
	private Closure $_callback;


	/**
	 * PaginationIteration constructor.
	 * @param ActiveQuery $activeQuery
	 * @param array $config
	 * @throws Exception
	 */
	public function __construct(ActiveQuery $activeQuery, array $config = [])
	{
		parent::__construct($config);
		$this->activeQuery = $activeQuery;
	}


	public function clean()
	{
		unset($this->activeQuery, $this->_callback, $this->_group);
		$this->_offset = 0;
		$this->_limit = 100;
		$this->_max = 0;
		$this->_length = 0;;
	}


	/**
	 * recover class by clone
	 */
	public function __clone()
	{
		$this->clean();
	}


	/**
	 * @param array|Closure $callback
	 * @throws Exception
	 */
	public function setCallback(array|Closure $callback)
	{
		if (!is_callable($callback, true)) {
			throw new Exception('非法回调函数~');
		}
		$this->_callback = $callback;
	}


	/**
	 * @param int $number
	 * @return Pagination
	 */
	public function setOffset(int $number): static
	{
		if ($number < 0) {
			$number = 0;
		}
		$this->_offset = $number;
		return $this;
	}


	/**
	 * @param int $number
	 * @return Pagination
	 */
	public function setLimit(int $number): static
	{
		if ($number < 1) {
			$number = 100;
		} else if ($number > 5000) {
			$number = 5000;
		}
		$this->_limit = $number;
		return $this;
	}


	/**
	 * @param int $number
	 * @return Pagination
	 */
	public function setMax(int $number): static
	{
		if ($number < 0) {
			return $this;
		}
		$this->_max = $number;
		return $this;
	}


	/**
	 * @param array $param
	 * @return void
	 * @throws Exception
	 */
	public function plunk(array $param = [])
	{
		$this->loop($param);
	}


	/**
	 * 轮训
	 * @param $param
	 * @return array
	 * @throws Exception
	 */
	public function loop($param): array
	{
		if ($this->_max > 0 && $this->_length >= $this->_max) {
			return $this->output();
		}
		[$length, $data] = $this->get();

		$this->executed($data, $param);

		unset($data);
		if ($length < $this->_limit) {
			return $this->output();
		}
		return $this->loop($param);
	}


	/**
	 * @return array
	 */
	public function output(): array
	{
		return [];
	}


	/**
	 * @param $data
	 * @param $param
	 * @throws Exception
	 */
	private function executed($data, $param): void
	{
		try {
			call_user_func($this->_callback, $data, $param);
		} catch (\Throwable $exception) {
			$this->addError($exception, 'throwable');
		}
	}


	/**
	 * @return array|Collection
	 */
	private function get(): Collection|array
	{
		if ($this->_max > 0 && $this->_length + $this->_limit > $this->_max) {
			$this->_limit = $this->_length + $this->_limit - $this->_max;
		}
		$data = $this->activeQuery->limit($this->_offset, $this->_limit)->get();
		$this->_offset += $this->_limit;

		if (is_array($data)) {
			$size = count($data);
		} else {
			$size = $data->size();
		}
		$this->_length += $size;
		return [$size, $data];
	}

}
