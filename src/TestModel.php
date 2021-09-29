<?php

namespace Database;


use Database\Model;

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
