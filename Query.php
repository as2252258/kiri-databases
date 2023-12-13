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

/**
 * Class Query
 * @package Database
 */
class Query extends QueryTrait implements ISqlBuilder
{


    /**
     * @var SqlBuilder
     */
    protected SqlBuilder $builder;


    /**
     * @throws
     */
    public function __construct()
    {
        $this->builder = SqlBuilder::builder($this);

        parent::__construct();
    }

    /**
     * @return string
     * @throws
     */
    public function getSql(): string
    {
        return $this->builder->get();
    }


    /**
     * @return string
     * @throws
     */
    public function getCondition(): string
    {
        return $this->builder->getCondition();
    }


}
