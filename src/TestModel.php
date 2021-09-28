<?php

namespace Database;


use Database\Base\Model;

/**
 *
 */
class TestModel extends Model
{


	protected string $connection = '';


	protected string $table = '';


	public ?string $primary = '';

}


TestModel::query()->get();
