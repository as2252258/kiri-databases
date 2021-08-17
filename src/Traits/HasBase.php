<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 15:47
 */
declare(strict_types=1);

namespace Database\Traits;

use Database\ActiveRecord;
use Database\Collection;
use Database\IOrm;
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

	/** @var ActiveRecord|Collection */
	protected Collection|ActiveRecord $data;

	/**
	 * @var IOrm|ActiveRecord
	 */
	protected mixed $model;

	protected mixed $value = [];


	/** @var Relation $_relation */
	protected Relation $_relation;

    /**
     * HasBase constructor.
     * @param IOrm $model
     * @param $primaryId
     * @param $value
     * @param Relation $relation
     * @throws Exception
     */
	public function __construct(mixed $model, $primaryId, $value, Relation $relation)
	{
		if (!class_exists($model)) {
			throw new Exception('Model must implement ' . ActiveRecord::class);
		}
		if (!in_array(IOrm::class, class_implements($model))) {
			throw new Exception('Model must implement ' . ActiveRecord::class);
		}
		if (is_array($value)) {
			if (empty($value)) $value = [];
			$_model = $model::find()->whereIn($primaryId, $value);
		} else {
			$_model = $model::find()->where(['t1.' . $primaryId => $value]);
		}

		$this->_relation = $relation->bindIdentification($model, $_model);

		$this->model = $model;
		$this->value = $value;
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
