<?php

declare(strict_types=1);

namespace Database\Base;


use Database\ActiveQuery;
use Database\ModelInterface;
use Exception;


/**
 * Class CollectionIterator
 * @package Database\Base
 */
class CollectionIterator extends \ArrayIterator
{

	private ModelInterface|string $model;



	/**
	 * CollectionIterator constructor.
	 * @param $model
	 * @param array $array
	 * @param int $flags
	 * @throws Exception
	 */
	public function __construct($model, array $array = [], int $flags = 0)
	{
		$this->model = $model;
		parent::__construct($array, $flags);
	}


	/**
	 * @param $current
	 * @return ModelInterface
	 * @throws Exception
	 */
	protected function newModel($current): ModelInterface
	{
		return $this->model->populates($current);
	}


	/**
	 * @throws Exception
	 */
	public function current(): ModelInterface
	{
		if (is_array($current = parent::current())) {
			$current = $this->newModel($current);
		}
		return $current;
	}


}
