<?php

declare(strict_types=1);
namespace Database\Base;


use Database\Condition\BetweenCondition;
use Database\Condition\InCondition;
use Database\Condition\LikeCondition;
use Database\Condition\LLikeCondition;
use Database\Condition\MathematicsCondition;
use Database\Condition\NotBetweenCondition;
use Database\Condition\NotInCondition;
use Database\Condition\NotLikeCondition;
use Database\Condition\RLikeCondition;

/**
 * Class ConditionClassMap
 * @package Database\Base
 */
class ConditionClassMap
{

	public static array $conditionMap = [
		'IN'          => [
			'class' => InCondition::class
		],
		'NOT IN'      => [
			'class' => NotInCondition::class
		],
		'LIKE'        => [
			'class' => LikeCondition::class
		],
		'NOT LIKE'    => [
			'class' => NotLikeCondition::class
		],
		'LLike'       => [
			'class' => LLikeCondition::class
		],
		'RLike'       => [
			'class' => RLikeCondition::class
		],
		'EQ'          => [
			'class' => MathematicsCondition::class,
			'type'  => 'EQ'
		],
		'NEQ'         => [
			'class' => MathematicsCondition::class,
			'type'  => 'NEQ'
		],
		'GT'          => [
			'class' => MathematicsCondition::class,
			'type'  => 'GT'
		],
		'EGT'         => [
			'class' => MathematicsCondition::class,
			'type'  => 'EGT'
		],
		'LT'          => [
			'class' => MathematicsCondition::class,
			'type'  => 'LT'
		],
		'ELT'         => [
			'class' => MathematicsCondition::class,
			'type'  => 'ELT'
		],
		'BETWEEN'     => BetweenCondition::class,
		'NOT BETWEEN' => NotBetweenCondition::class,
	];

}
