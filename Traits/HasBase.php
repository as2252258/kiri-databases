<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 15:47
 */
declare(strict_types=1);

namespace Database\Traits;

use Database\ModelInterface;
use Database\Collection;
use Database\Relation;
use Exception;

/**
 * Class HasBase
 * @package Database
 *
 * @include Query
 */
abstract class HasBase implements \Database\Traits\Relation
{

	/** @var ModelInterface|Collection */
	protected Collection|ModelInterface $data;

	/**
	 * @var ModelInterface
	 */
	protected mixed $model;

	protected mixed $value = [];


	/** @var Relation $_relation */
	protected Relation $_relation;

	/**
	 * HasBase constructor.
	 * @param ModelInterface $model
	 * @param string $primaryId
	 * @param $value
	 * @param Relation $relation
	 * @throws Exception
	 */
	public function __construct(mixed $model, public string $primaryId, $value, Relation $relation)
	{
		if (!class_exists($model)) {
			throw new Exception('Model must implement ' . $model);
		}
		if (!in_array(ModelInterface::class, class_implements($model))) {
			throw new Exception('Model must implement ' . $model);
		}
		if (is_array($value)) {
			if (empty($value)) $value = [];
			$_model = $model::query()->whereIn($primaryId, $value);
		} else {
			$_model = $model::query()->where(['t1.' . $primaryId => $value]);
		}

		$this->value = is_array($value) ? json_encode($value,JSON_UNESCAPED_UNICODE) : $value;
		$this->_relation = $relation->bindIdentification($model . '_' . $primaryId . '_' . $this->value, $_model);
		$this->model = $model;
	}


	/**
	 * @param $name
	 * @return mixed
	 */
	public function __get($name): mixed
	{
		if (empty($this->value)) {
			return null;
		}
		return $this->get();
	}
}
